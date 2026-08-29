<?php

namespace App\Domains\Queues\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use UnexpectedValueException;

class QueueProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactSensitiveData) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchQueue
    {
        $resourceId = $this->stringValue($snapshot['id'] ?? null);
        $name = $this->stringValue($snapshot['name'] ?? null);

        if ($resourceId === null || $name === null) {
            throw new UnexpectedValueException('Switch queue response is missing required metadata.');
        }

        $musicOnHoldReference = $this->stringValue($snapshot['moh'] ?? null);
        $queue = SwitchQueue::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(), 'switch_resource_id' => $resourceId,
        ]);
        $queue->fill([
            'name' => $name,
            'strategy' => in_array($snapshot['strategy'] ?? null, ['round_robin', 'most_idle'], true) ? $snapshot['strategy'] : 'round_robin',
            'agent_ring_timeout' => max(1, (int) ($snapshot['agent_ring_timeout'] ?? 15)),
            'agent_wrapup_time' => max(0, (int) ($snapshot['agent_wrapup_time'] ?? 0)),
            'connection_timeout' => max(0, (int) ($snapshot['connection_timeout'] ?? 3600)),
            'max_queue_size' => max(0, (int) ($snapshot['max_queue_size'] ?? 0)),
            'ring_simultaneously' => max(1, (int) ($snapshot['ring_simultaneously'] ?? 1)),
            'enter_when_empty' => (bool) ($snapshot['enter_when_empty'] ?? true),
            'record_caller' => (bool) ($snapshot['record_caller'] ?? false),
            'caller_exit_key' => in_array($snapshot['caller_exit_key'] ?? null, ['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#'], true) ? $snapshot['caller_exit_key'] : '#',
            'music_on_hold_reference' => $musicOnHoldReference,
            'music_on_hold_media_id' => $musicOnHoldReference === null ? null : $account->media()->where('switch_resource_id', $musicOnHoldReference)->value('media_id'),
            'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $queue->exists ? $queue->projection_version + 1 : 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $queue->deleted_at = null;
        $queue->save();
        $seen = [];

        foreach (is_array($snapshot['agents'] ?? null) ? $snapshot['agents'] : [] as $switchUserId) {
            if (! is_string($switchUserId) || $switchUserId === '') {
                continue;
            }

            $agent = $queue->agents()->updateOrCreate(
                ['switch_user_resource_id' => $switchUserId],
                ['switch_extension_id' => $account->extensions()->where('switch_resource_id', $switchUserId)->value('extension_id')],
            );
            $seen[] = $agent->getKey();
        }

        $queue->agents()->when($seen !== [], fn ($query) => $query->whereNotIn('queue_agent_id', $seen))->delete();

        if ($seen === []) {
            $queue->agents()->delete();
        }

        return $queue->load(['agents.extension', 'musicOnHoldMedia', 'switchAccount.media']);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
