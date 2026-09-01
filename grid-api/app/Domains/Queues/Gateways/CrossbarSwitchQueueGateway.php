<?php

namespace App\Domains\Queues\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchQueueGateway;
use Generator;
use GridPbx\Switch\Domains\Queues\AcdcCapabilityClient;
use GridPbx\Switch\Domains\Queues\Dto\QueueAnnouncementsWriteData;
use GridPbx\Switch\Domains\Queues\Dto\QueueWriteData;
use GridPbx\Switch\Domains\Queues\QueueResourceClient;

class CrossbarSwitchQueueGateway implements SwitchQueueGateway
{
    public function __construct(
        private readonly QueueResourceClient $queues,
        private readonly AcdcCapabilityClient $capabilities,
    ) {}

    public function capabilities(SwitchAccount $account): array
    {
        return $this->capabilities->discover($account->switch_account_id)->toArray();
    }

    public function statistics(SwitchAccount $account): array
    {
        return $this->queues->statistics($account->switch_account_id)->toArray();
    }

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->queues->allDetails($account->switch_account_id) as $queue) {
            yield $queue->toArray();
        }
    }

    public function create(SwitchAccount $account, array $data): array
    {
        return $this->queues->create($account->switch_account_id, $this->writeData($data))->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->queues->update($account->switch_account_id, $resourceId, $this->writeData($data))->toArray();
    }

    public function replaceRoster(SwitchAccount $account, string $resourceId, array $switchUserIds): array
    {
        $this->queues->replaceRoster($account->switch_account_id, $resourceId, $switchUserIds);

        return $this->queues->get($account->switch_account_id, $resourceId)->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->queues->delete($account->switch_account_id, $resourceId);
    }

    /** @param array<string, mixed> $data */
    private function writeData(array $data): QueueWriteData
    {
        return new QueueWriteData(
            name: (string) $data['name'], strategy: (string) $data['strategy'],
            agentRingTimeout: (int) $data['agent_ring_timeout'], agentWrapupTime: (int) $data['agent_wrapup_time'],
            connectionTimeout: (int) $data['connection_timeout'], maxQueueSize: (int) $data['max_queue_size'],
            ringSimultaneously: (int) $data['ring_simultaneously'], enterWhenEmpty: (bool) $data['enter_when_empty'],
            recordCaller: (bool) $data['record_caller'], callerExitKey: (string) $data['caller_exit_key'],
            musicOnHoldMediaId: $data['switch_music_on_hold_reference'] ?? null,
            announceMediaId: $data['switch_announce_media_reference'] ?? null,
            maxPriority: $data['switch_max_priority'] ?? null,
            announcements: is_array($data['switch_announcements'] ?? null)
                ? new QueueAnnouncementsWriteData(
                    interval: (int) $data['switch_announcements']['interval'],
                    positionAnnouncementsEnabled: (bool) $data['switch_announcements']['position_announcements_enabled'],
                    waitTimeAnnouncementsEnabled: (bool) $data['switch_announcements']['wait_time_announcements_enabled'],
                    inTheQueueMediaId: $data['switch_announcements']['media']['in_the_queue'] ?? null,
                    increaseInCallVolumeMediaId: $data['switch_announcements']['media']['increase_in_call_volume'] ?? null,
                    estimatedWaitTimeMediaId: $data['switch_announcements']['media']['the_estimated_wait_time_is'] ?? null,
                    positionMediaId: $data['switch_announcements']['media']['you_are_at_position'] ?? null,
                )
                : null,
            cdrUrl: $data['switch_cdr_url'] ?? null,
            recordingUrl: $data['switch_recording_url'] ?? null,
        );
    }
}
