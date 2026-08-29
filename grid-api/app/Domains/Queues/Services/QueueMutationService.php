<?php

namespace App\Domains\Queues\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchQueueGateway;
use App\Domains\Queues\Models\SwitchQueue;
use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class QueueMutationService
{
    public function __construct(
        private readonly SwitchQueueGateway $gateway,
        private readonly QueueProjectionService $projection,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(SwitchAccount $account, User $actor, array $data, ?string $ipAddress = null): SwitchQueue
    {
        $resolved = $this->resolve($account, $data, null);
        $resourceId = null;

        try {
            $snapshot = $this->gateway->create($account, $resolved);
            $resourceId = is_string($snapshot['id'] ?? null) ? $snapshot['id'] : null;

            if ($resourceId === null) {
                throw new \UnexpectedValueException('Switch queue create response is missing its identifier.');
            }

            $snapshot = $this->gateway->replaceRoster($account, $resourceId, $resolved['resolved_agent_ids']);

            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchQueue {
                $queue = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'queue.created', 'succeeded', $queue->switch_resource_id, [], $ipAddress, 'queue');

                return $queue;
            });
        } catch (Throwable $exception) {
            if ($resourceId !== null) {
                try {
                    $this->gateway->delete($account, $resourceId);
                } catch (Throwable) {
                }
            }

            $this->throwTranslated($exception);
        }
    }

    /** @param array<string, mixed> $data */
    public function update(SwitchAccount $account, SwitchQueue $queue, User $actor, array $data, ?string $ipAddress = null): SwitchQueue
    {
        $resolved = $this->resolve($account, $data, $queue);
        $previousData = $this->writeDataFromModel($queue);
        $previousAgentIds = $queue->agents()->pluck('switch_user_resource_id')->all();

        try {
            $this->gateway->update($account, $queue->switch_resource_id, $resolved);
            $snapshot = $this->gateway->replaceRoster($account, $queue->switch_resource_id, $resolved['resolved_agent_ids']);

            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchQueue {
                $updated = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'queue.updated', 'succeeded', $updated->switch_resource_id, [], $ipAddress, 'queue');

                return $updated;
            });
        } catch (Throwable $exception) {
            $this->restore($account, $queue->switch_resource_id, $previousData, $previousAgentIds);
            $this->throwTranslated($exception);
        }
    }

    public function delete(SwitchAccount $account, SwitchQueue $queue, User $actor, ?string $ipAddress = null): void
    {
        foreach ($account->callflows()->get() as $callflow) {
            if ($this->containsQueue($callflow->switch_json['flow'] ?? null, $queue->switch_resource_id)) {
                throw ValidationException::withMessages(['queue' => ['Remove this queue from call routing before deleting it.']]);
            }
        }

        $agentIds = $queue->agents()->pluck('switch_user_resource_id')->all();
        $configurationDeleted = false;

        try {
            $this->gateway->replaceRoster($account, $queue->switch_resource_id, []);
            $this->gateway->delete($account, $queue->switch_resource_id);
            $configurationDeleted = true;

            DB::transaction(function () use ($account, $actor, $queue, $ipAddress): void {
                $queue->delete();
                $this->audit->record($actor, $account, 'queue.deleted', 'succeeded', $queue->switch_resource_id, [], $ipAddress, 'queue');
            });
        } catch (Throwable $exception) {
            if (! $configurationDeleted) {
                try {
                    $this->gateway->replaceRoster($account, $queue->switch_resource_id, $agentIds);
                } catch (Throwable) {
                }
            }

            $this->throwTranslated($exception);
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolve(SwitchAccount $account, array $data, ?SwitchQueue $queue): array
    {
        $agentIds = array_values($data['agent_ids']);
        $agents = $account->extensions()->whereIn('id', $agentIds)->get();

        if ($agents->count() !== count($agentIds) || $agents->contains(fn ($agent): bool => empty($agent->switch_resource_id))) {
            throw ValidationException::withMessages(['agent_ids' => ['One or more selected agents are unavailable for this account.']]);
        }

        $current = is_array($queue?->switch_json) ? $queue->switch_json : [];
        $musicOnHold = $this->resolveMediaReference($account, $data['music_on_hold_media_id'] ?? null, 'music_on_hold_media_id', $current['moh'] ?? null);
        $announce = $this->resolveMediaReference($account, $data['announce_media_id'] ?? null, 'announce_media_id', $current['announce'] ?? null);
        $currentAnnouncements = is_array($current['announcements'] ?? null) ? $current['announcements'] : [];
        $currentAnnouncementMedia = is_array($currentAnnouncements['media'] ?? null) ? $currentAnnouncements['media'] : [];
        $announcementMedia = [];

        foreach ([
            'in_the_queue' => 'announcement_in_the_queue_media_id',
            'increase_in_call_volume' => 'announcement_increase_in_call_volume_media_id',
            'the_estimated_wait_time_is' => 'announcement_estimated_wait_time_media_id',
            'you_are_at_position' => 'announcement_position_media_id',
        ] as $switchKey => $publicField) {
            $reference = $this->resolveMediaReference($account, $data[$publicField] ?? null, $publicField, $currentAnnouncementMedia[$switchKey] ?? null);

            if ($reference !== null) {
                $announcementMedia[$switchKey] = $reference;
            }
        }

        $announcements = ($data['announcements_enabled'] ?? false) ? [
            'interval' => (int) $data['announcement_interval'],
            'position_announcements_enabled' => (bool) $data['position_announcements_enabled'],
            'wait_time_announcements_enabled' => (bool) $data['wait_time_announcements_enabled'],
            'media' => $announcementMedia,
        ] : null;

        return [
            ...$data,
            'resolved_agent_ids' => $agents->pluck('switch_resource_id')->values()->all(),
            'switch_music_on_hold_reference' => $musicOnHold,
            'switch_announce_media_reference' => $announce,
            'switch_max_priority' => $queue === null ? ($data['max_priority'] ?? null) : $this->integerValue($current['max_priority'] ?? null),
            'switch_announcements' => $announcements,
            'switch_cdr_url' => $queue === null ? null : $this->stringValue($current['cdr_url'] ?? null),
            'switch_recording_url' => $queue === null ? null : $this->stringValue($current['recording_url'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function writeDataFromModel(SwitchQueue $queue): array
    {
        return [
            'name' => $queue->name, 'strategy' => $queue->strategy,
            'agent_ring_timeout' => $queue->agent_ring_timeout, 'agent_wrapup_time' => $queue->agent_wrapup_time,
            'connection_timeout' => $queue->connection_timeout, 'max_queue_size' => $queue->max_queue_size,
            'ring_simultaneously' => $queue->ring_simultaneously, 'enter_when_empty' => $queue->enter_when_empty,
            'record_caller' => $queue->record_caller, 'caller_exit_key' => $queue->caller_exit_key,
            'switch_music_on_hold_reference' => $queue->music_on_hold_reference,
            'switch_announce_media_reference' => $this->stringValue($queue->switch_json['announce'] ?? null),
            'switch_max_priority' => $this->integerValue($queue->switch_json['max_priority'] ?? null),
            'switch_announcements' => is_array($queue->switch_json['announcements'] ?? null) ? $queue->switch_json['announcements'] : null,
            'switch_cdr_url' => $this->stringValue($queue->switch_json['cdr_url'] ?? null),
            'switch_recording_url' => $this->stringValue($queue->switch_json['recording_url'] ?? null),
        ];
    }

    private function resolveMediaReference(SwitchAccount $account, mixed $publicId, string $field, mixed $currentReference): ?string
    {
        if (is_string($publicId) && $publicId !== '') {
            $media = $account->media()->where('id', $publicId)->first();

            if ($media === null) {
                throw ValidationException::withMessages([$field => ['The selected media is unavailable for this account.']]);
            }

            return $media->switch_resource_id;
        }

        $current = $this->stringValue($currentReference);

        if ($current !== null && ! $account->media()->where('switch_resource_id', $current)->exists()) {
            return $current;
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integerValue(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 && $value <= 255 ? $value : null;
    }

    /** @param array<string, mixed> $data
     * @param  list<string>  $agentIds
     */
    private function restore(SwitchAccount $account, string $resourceId, array $data, array $agentIds): void
    {
        try {
            $this->gateway->update($account, $resourceId, $data);
            $this->gateway->replaceRoster($account, $resourceId, $agentIds);
        } catch (Throwable) {
        }
    }

    private function containsQueue(mixed $node, string $resourceId): bool
    {
        if (! is_array($node)) {
            return false;
        }

        $module = $node['module'] ?? null;
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        if (in_array($module, ['acdc_member', 'acdc_queue'], true) && ($data['id'] ?? null) === $resourceId) {
            return true;
        }

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if ($this->containsQueue($child, $resourceId)) {
                return true;
            }
        }

        return false;
    }

    private function throwTranslated(Throwable $exception): never
    {
        if ($exception instanceof SwitchRequestException && in_array($exception->statusCode, [404, 501, 503], true)) {
            throw ValidationException::withMessages(['queue' => ['Queue and agent management is unavailable because Switch ACDc is not enabled.']]);
        }

        throw $exception;
    }
}
