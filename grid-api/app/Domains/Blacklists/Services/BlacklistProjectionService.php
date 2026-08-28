<?php

namespace App\Domains\Blacklists\Services;

use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class BlacklistProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redact) {}
    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot, bool $active): SwitchBlacklist
    {
        $id = is_string($snapshot['id'] ?? null) && $snapshot['id'] !== '' ? $snapshot['id'] : null;
        $name = is_string($snapshot['name'] ?? null) && trim($snapshot['name']) !== '' ? $snapshot['name'] : null;
        $numbers = $snapshot['numbers'] ?? [];
        if ($id === null || $name === null || ! is_array($numbers)) throw new UnexpectedValueException('Switch blacklist response is missing required metadata.');

        return DB::transaction(function () use ($account, $snapshot, $active, $id, $name, $numbers): SwitchBlacklist {
            $blacklist = SwitchBlacklist::withTrashed()->firstOrNew(['switch_account_id' => $account->getKey(), 'switch_resource_id' => $id]);
            $blacklist->fill(['name' => $name, 'should_block_anonymous' => ($snapshot['should_block_anonymous'] ?? false) === true, 'is_active' => $active, 'flags' => is_array($snapshot['flags'] ?? null) ? array_values($snapshot['flags']) : [], 'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => $blacklist->exists ? $blacklist->projection_version + 1 : 1, 'switch_json' => $this->redact->handle($snapshot)]);
            $blacklist->deleted_at = null; $blacklist->save();
            $seen = [];
            foreach ($numbers as $number => $metadata) {
                if (! is_string($number) || $number === '') continue;
                $seen[] = $number;
                $blacklist->entries()->updateOrCreate(['number' => $number], ['metadata' => is_array($metadata) ? $metadata : []]);
            }
            $blacklist->entries()->when($seen !== [], fn ($query) => $query->whereNotIn('number', $seen))->when($seen === [], fn ($query) => $query)->delete();
            return $blacklist->load('entries');
        });
    }
}
