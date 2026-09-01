<?php

namespace App\Domains\Queues\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchAgentGateway;
use App\Domains\Queues\Models\SwitchQueue;
use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class AgentService
{
    public function __construct(
        private readonly SwitchAgentGateway $gateway,
        private readonly QueueCapabilityService $capabilities,
        private readonly AuditService $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function all(SwitchAccount $account): array
    {
        $agents = [];

        foreach ($account->queues()->with('agents.extension')->get() as $queue) {
            foreach ($queue->agents as $membership) {
                if ($membership->extension === null) {
                    continue;
                }

                $id = (string) $membership->extension->id;
                $agents[$id] ??= [
                    'id' => $id,
                    'name' => $membership->extension->display_name ?? $membership->extension->extension ?? 'Unnamed agent',
                    'extension' => $membership->extension->extension,
                    'queues' => [],
                ];
                $agents[$id]['queues'][] = ['id' => $queue->id, 'name' => $queue->name];
            }
        }

        return array_values($agents);
    }

    /** @return array<string, mixed> */
    public function status(SwitchAccount $account, string $agentId): array
    {
        $agent = $this->agent($account, $agentId);

        try {
            $status = $this->gateway->status($account, $agent->switch_resource_id);
        } catch (SwitchRequestException $exception) {
            $this->throwCapabilityError($exception);
        }

        return [
            'id' => $agent->id,
            'status' => is_string($status['status'] ?? null) ? $status['status'] : null,
            'timestamp' => is_int($status['timestamp'] ?? null) ? $status['timestamp'] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function queueMemberships(SwitchAccount $account, string $agentId): array
    {
        $agent = $this->agent($account, $agentId);
        $this->ensureQueueConfigurationAvailable($account);

        try {
            return $this->membershipPayload($account, $agent, $this->gateway->queueIds($account, $agent->switch_resource_id));
        } catch (SwitchRequestException $exception) {
            $this->throwMembershipCapabilityError($exception);
        }
    }

    /** @return array<string, mixed> */
    public function updateQueueMembership(
        SwitchAccount $account,
        string $agentId,
        string $queueId,
        string $action,
        bool $confirmLastQueue,
        User $actor,
        ?string $ipAddress = null,
    ): array {
        $agent = $this->agent($account, $agentId);
        $queue = $account->queues()->where('id', $queueId)->firstOrFail();
        $capabilities = $this->capabilities->get($account);

        if (! $capabilities['configuration_available'] || ! $capabilities['live_agent_controls_available']) {
            throw ValidationException::withMessages([
                'queue_id' => ['Agent Queue membership controls require both Queue configuration and live ACDc controls.'],
            ]);
        }

        try {
            $removesLastQueue = false;

            if ($action === 'logout') {
                $currentQueueIds = array_values(array_unique($this->gateway->queueIds(
                    $account,
                    $agent->switch_resource_id,
                )));
                $removesLastQueue = count($currentQueueIds) === 1
                    && $currentQueueIds[0] === $queue->switch_resource_id;

                if ($removesLastQueue && ! $confirmLastQueue) {
                    throw ValidationException::withMessages([
                        'confirm_last_queue' => [
                            'Leaving this final Queue removes the User from the Switch Agent list. Confirm the final Queue removal to continue.',
                        ],
                    ]);
                }
            }

            $queueIds = $this->gateway->updateQueueMembership(
                $account,
                $agent->switch_resource_id,
                $action,
                $queue->switch_resource_id,
            );
            $payload = $this->membershipPayload($account, $agent, $queueIds, true);
            $this->audit->record(
                $actor,
                $account,
                'agent.queue_membership_requested',
                'succeeded',
                $agent->switch_resource_id,
                [
                    'agent_id' => $agent->id,
                    'queue_id' => $queue->id,
                    'action' => $action,
                    'removed_last_queue' => $removesLastQueue,
                ],
                $ipAddress,
                'agent',
            );

            return $payload;
        } catch (SwitchRequestException $exception) {
            $this->recordMembershipFailure($actor, $account, $agent, $queue, $action, $exception, $ipAddress);
            $this->throwMembershipCapabilityError($exception);
        } catch (Throwable $exception) {
            $this->recordMembershipFailure($actor, $account, $agent, $queue, $action, $exception, $ipAddress);
            throw $exception;
        }
    }

    public function updateStatus(SwitchAccount $account, string $agentId, string $status, ?int $pauseTimeout, User $actor, ?string $ipAddress = null): void
    {
        $agent = $this->agent($account, $agentId);

        try {
            $this->gateway->updateStatus($account, $agent->switch_resource_id, $status, $pauseTimeout);
            $this->audit->record($actor, $account, 'agent.status_requested', 'succeeded', $agent->switch_resource_id, [
                'agent_id' => $agent->id, 'status' => $status, 'pause_timeout' => $pauseTimeout,
            ], $ipAddress, 'agent');
        } catch (SwitchRequestException $exception) {
            $this->audit->record($actor, $account, 'agent.status_requested', 'failed', $agent->switch_resource_id, [
                'agent_id' => $agent->id, 'status' => $status, 'error' => $exception->getMessage(),
            ], $ipAddress, 'agent');
            $this->throwCapabilityError($exception);
        } catch (Throwable $exception) {
            $this->audit->record($actor, $account, 'agent.status_requested', 'failed', $agent->switch_resource_id, [
                'agent_id' => $agent->id, 'status' => $status, 'error' => $exception->getMessage(),
            ], $ipAddress, 'agent');
            throw $exception;
        }
    }

    private function agent(SwitchAccount $account, string $id): SwitchExtension
    {
        return $account->extensions()->where('id', $id)->whereHas('queueMemberships')->firstOrFail();
    }

    private function ensureQueueConfigurationAvailable(SwitchAccount $account): void
    {
        if (! $this->capabilities->get($account)['configuration_available']) {
            throw ValidationException::withMessages([
                'agent' => ['Agent Queue memberships are unavailable because Switch Queue configuration is not enabled.'],
            ]);
        }
    }

    /**
     * Reconcile only Queues already projected for this account. Unknown Switch
     * Queue identifiers remain private and are surfaced only as a count.
     *
     * @param  list<string>  $switchQueueIds
     * @return array<string, mixed>
     */
    private function membershipPayload(
        SwitchAccount $account,
        SwitchExtension $agent,
        array $switchQueueIds,
        bool $reconcile = false,
    ): array {
        $switchQueueIds = array_values(array_unique($switchQueueIds));
        $queues = $account->queues()->orderBy('name')->get(['queue_id', 'id', 'switch_resource_id', 'name']);
        $knownByResourceId = $queues->keyBy('switch_resource_id');

        if ($reconcile) {
            $this->reconcileKnownMemberships($queues, $agent, $switchQueueIds);
        }

        $assigned = $queues
            ->filter(fn (SwitchQueue $queue): bool => in_array($queue->switch_resource_id, $switchQueueIds, true))
            ->map(fn (SwitchQueue $queue): array => ['id' => $queue->id, 'name' => $queue->name])
            ->values();
        $available = $queues
            ->reject(fn (SwitchQueue $queue): bool => in_array($queue->switch_resource_id, $switchQueueIds, true))
            ->map(fn (SwitchQueue $queue): array => ['id' => $queue->id, 'name' => $queue->name])
            ->values();

        return [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->display_name ?? $agent->extension ?? 'Unnamed agent',
                'extension' => $agent->extension,
            ],
            'assigned_queues' => $assigned->all(),
            'available_queues' => $available->all(),
            'unresolved_queues' => collect($switchQueueIds)
                ->reject(fn (string $switchQueueId): bool => $knownByResourceId->has($switchQueueId))
                ->count(),
            'agent_active' => $switchQueueIds !== [],
            'observed_at' => now()->toIso8601String(),
        ];
    }

    /** @param Collection<int, SwitchQueue> $queues
     * @param  list<string>  $switchQueueIds
     */
    private function reconcileKnownMemberships(Collection $queues, SwitchExtension $agent, array $switchQueueIds): void
    {
        foreach ($queues as $queue) {
            if (in_array($queue->switch_resource_id, $switchQueueIds, true)) {
                $queue->agents()->updateOrCreate(
                    ['switch_user_resource_id' => $agent->switch_resource_id],
                    ['switch_extension_id' => $agent->getKey()],
                );

                continue;
            }

            $queue->agents()->where('switch_user_resource_id', $agent->switch_resource_id)->delete();
        }
    }

    private function recordMembershipFailure(
        User $actor,
        SwitchAccount $account,
        SwitchExtension $agent,
        SwitchQueue $queue,
        string $action,
        Throwable $exception,
        ?string $ipAddress,
    ): void {
        $this->audit->record(
            $actor,
            $account,
            'agent.queue_membership_requested',
            'failed',
            $agent->switch_resource_id,
            [
                'agent_id' => $agent->id,
                'queue_id' => $queue->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ],
            $ipAddress,
            'agent',
        );
    }

    private function throwMembershipCapabilityError(SwitchRequestException $exception): never
    {
        if (in_array($exception->statusCode, [404, 501, 503], true)) {
            throw ValidationException::withMessages([
                'agent' => ['Agent Queue membership controls are unavailable because Switch ACDc is not enabled.'],
            ]);
        }

        throw $exception;
    }

    private function throwCapabilityError(SwitchRequestException $exception): never
    {
        if (in_array($exception->statusCode, [404, 501, 503], true)) {
            throw ValidationException::withMessages(['agent' => ['Live agent controls are unavailable because Switch ACDc is not enabled.']]);
        }

        throw $exception;
    }
}
