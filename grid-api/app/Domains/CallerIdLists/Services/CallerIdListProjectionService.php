<?php

namespace App\Domains\CallerIdLists\Services;

use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class CallerIdListProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redact) {}

    /**
     * @param  array{list: array<string, mixed>, entries: list<array<string, mixed>>}  $snapshot
     */
    public function project(SwitchAccount $account, array $snapshot): SwitchCallerIdList
    {
        $data = $snapshot['list'];
        $resourceId = is_string($data['id'] ?? null) && $data['id'] !== '' ? $data['id'] : null;
        $name = is_string($data['name'] ?? null) && trim($data['name']) !== '' ? trim($data['name']) : null;

        if ($resourceId === null || $name === null) {
            throw new UnexpectedValueException('Switch Caller-ID List response is missing required metadata.');
        }

        return DB::transaction(function () use ($account, $snapshot, $data, $resourceId, $name): SwitchCallerIdList {
            $list = SwitchCallerIdList::withTrashed()->firstOrNew([
                'switch_account_id' => $account->getKey(),
                'switch_resource_id' => $resourceId,
            ]);
            $list->fill([
                'name' => $name,
                'description' => is_string($data['description'] ?? null) ? $data['description'] : null,
                'organization' => is_string($data['org'] ?? null) ? $data['org'] : null,
                'last_synced_at' => now(),
                'sync_status' => ProjectionStatus::Healthy,
                'projection_version' => $list->exists ? $list->projection_version + 1 : 1,
                'switch_json' => $this->redact->handle($data),
            ]);
            $list->deleted_at = null;
            $list->save();

            $seen = [];

            foreach ($snapshot['entries'] as $entry) {
                $entryId = is_string($entry['id'] ?? null) && $entry['id'] !== '' ? $entry['id'] : null;

                if ($entryId === null || ($entry['list_id'] ?? null) !== $resourceId) {
                    continue;
                }

                $seen[] = $entryId;
                $list->entries()->updateOrCreate(
                    ['switch_resource_id' => $entryId],
                    [
                        'display_name' => is_string($entry['displayname'] ?? null) ? $entry['displayname'] : null,
                        'number' => is_string($entry['number'] ?? null) ? $entry['number'] : null,
                        'pattern' => is_string($entry['pattern'] ?? null) ? $entry['pattern'] : null,
                        'switch_json' => $this->redact->handle($entry),
                    ],
                );
            }

            $list->entries()
                ->when($seen !== [], fn ($query) => $query->whereNotIn('switch_resource_id', $seen))
                ->when($seen === [], fn ($query) => $query)
                ->delete();

            return $list->load('entries');
        });
    }
}
