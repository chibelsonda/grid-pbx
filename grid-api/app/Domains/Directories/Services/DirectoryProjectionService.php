<?php

namespace App\Domains\Directories\Services;

use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use UnexpectedValueException;

class DirectoryProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactSensitiveData) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchDirectory
    {
        $resourceId = $this->stringValue($snapshot['id'] ?? null);
        $name = $this->stringValue($snapshot['name'] ?? null);

        if ($resourceId === null || $name === null) {
            throw new UnexpectedValueException('Switch directory response is missing required metadata.');
        }

        $directory = SwitchDirectory::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(), 'switch_resource_id' => $resourceId,
        ]);
        $directory->fill([
            'name' => $name, 'confirm_match' => (bool) ($snapshot['confirm_match'] ?? true),
            'min_dtmf' => max(1, (int) ($snapshot['min_dtmf'] ?? 3)),
            'max_dtmf' => max(0, (int) ($snapshot['max_dtmf'] ?? 0)),
            'sort_by' => in_array($snapshot['sort_by'] ?? null, ['first_name', 'last_name'], true) ? $snapshot['sort_by'] : 'last_name',
            'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $directory->exists ? $directory->projection_version + 1 : 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $directory->deleted_at = null;
        $directory->save();
        $seen = [];

        foreach (is_array($snapshot['users'] ?? null) ? $snapshot['users'] : [] as $member) {
            if (! is_array($member)) {
                continue;
            }

            $userId = $this->stringValue($member['user_id'] ?? null);
            $callflowId = $this->stringValue($member['callflow_id'] ?? null);

            if ($userId === null || $callflowId === null) {
                continue;
            }

            $extension = $account->extensions()->where('switch_resource_id', $userId)->first();
            $callflow = $account->callflows()->where('switch_resource_id', $callflowId)->first();
            $directory->members()->updateOrCreate(['switch_user_resource_id' => $userId], [
                'switch_extension_id' => $extension?->getKey(), 'switch_callflow_id' => $callflow?->getKey(),
                'switch_callflow_resource_id' => $callflowId,
            ]);
            $seen[] = $userId;
        }

        $directory->members()->when($seen !== [], fn ($query) => $query->whereNotIn('switch_user_resource_id', $seen))->delete();

        if ($seen === []) {
            $directory->members()->delete();
        }

        return $directory->load(['members.extension', 'members.callflow']);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
