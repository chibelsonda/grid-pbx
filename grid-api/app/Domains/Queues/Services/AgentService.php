<?php

namespace App\Domains\Queues\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Queues\Contracts\SwitchAgentGateway;
use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use Illuminate\Validation\ValidationException;
use Throwable;

class AgentService
{
    public function __construct(private readonly SwitchAgentGateway $gateway, private readonly AuditService $audit) {}

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

    private function throwCapabilityError(SwitchRequestException $exception): never
    {
        if (in_array($exception->statusCode, [404, 501, 503], true)) {
            throw ValidationException::withMessages(['agent' => ['Live agent controls are unavailable because Switch ACDc is not enabled.']]);
        }

        throw $exception;
    }
}
