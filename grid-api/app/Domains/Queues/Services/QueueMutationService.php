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
        $resolved = $this->resolve($account, $data);
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
        $resolved = $this->resolve($account, $data);
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
    private function resolve(SwitchAccount $account, array $data): array
    {
        $agentIds = array_values($data['agent_ids']);
        $agents = $account->extensions()->whereIn('id', $agentIds)->get();

        if ($agents->count() !== count($agentIds) || $agents->contains(fn ($agent): bool => empty($agent->switch_resource_id))) {
            throw ValidationException::withMessages(['agent_ids' => ['One or more selected agents are unavailable for this account.']]);
        }

        $media = empty($data['music_on_hold_media_id']) ? null : $account->media()->where('id', $data['music_on_hold_media_id'])->first();

        if (! empty($data['music_on_hold_media_id']) && $media === null) {
            throw ValidationException::withMessages(['music_on_hold_media_id' => ['The selected media is unavailable for this account.']]);
        }

        return [
            ...$data,
            'resolved_agent_ids' => $agents->pluck('switch_resource_id')->values()->all(),
            'switch_music_on_hold_reference' => $media?->switch_resource_id,
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
        ];
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
