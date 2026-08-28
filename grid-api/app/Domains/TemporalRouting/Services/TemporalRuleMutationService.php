<?php

namespace App\Domains\TemporalRouting\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleGateway;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TemporalRuleMutationService
{
    public function __construct(private readonly SwitchTemporalRuleGateway $gateway, private readonly TemporalRuleProjectionService $projection, private readonly AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(SwitchAccount $account, User $actor, array $data, ?string $ip = null): SwitchTemporalRule
    {
        $resourceId = null;
        try {
            $snapshot = $this->gateway->create($account, $data);
            $resourceId = is_string($snapshot['id'] ?? null) ? $snapshot['id'] : null;
            if ($resourceId === null) {
                throw new \UnexpectedValueException('Switch temporal rule create response is missing its identifier.');
            }

            return DB::transaction(function () use ($account, $actor, $ip, $snapshot) {
                $rule = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'temporal_rule.created', 'succeeded', $rule->switch_resource_id, [], $ip, 'temporal_rule');

                return $rule;
            });
        } catch (Throwable $e) {
            if ($resourceId !== null) {
                try {
                    $this->gateway->delete($account, $resourceId);
                } catch (Throwable) {
                }
            } throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(SwitchAccount $account, SwitchTemporalRule $rule, User $actor, array $data, ?string $ip = null): SwitchTemporalRule
    {
        $previous = $this->writeData($rule);
        try {
            $snapshot = $this->gateway->update($account, $rule->switch_resource_id, $data);

            return DB::transaction(function () use ($account, $actor, $ip, $snapshot) {
                $updated = $this->projection->project($account, $snapshot);
                $this->audit->record($actor, $account, 'temporal_rule.updated', 'succeeded', $updated->switch_resource_id, [], $ip, 'temporal_rule');

                return $updated;
            });
        } catch (Throwable $e) {
            try {
                $this->gateway->update($account, $rule->switch_resource_id, $previous);
            } catch (Throwable) {
            } throw $e;
        }
    }

    public function delete(SwitchAccount $account, SwitchTemporalRule $rule, User $actor, ?string $ip = null): void
    {
        if ($rule->ruleSetMemberships()->exists()) {
            throw ValidationException::withMessages(['rule' => ['Remove this rule from every rule set before deleting it.']]);
        }
        foreach ($account->callflows()->get() as $callflow) {
            if ($this->containsRule($callflow->switch_json['flow'] ?? null, $rule->switch_resource_id)) {
                throw ValidationException::withMessages(['rule' => ['Remove this rule from call routing before deleting it.']]);
            }
        }
        $this->gateway->delete($account, $rule->switch_resource_id);
        DB::transaction(function () use ($account, $actor, $rule, $ip): void {
            $rule->delete();
            $this->audit->record($actor, $account, 'temporal_rule.deleted', 'succeeded', $rule->switch_resource_id, [], $ip, 'temporal_rule');
        });
    }

    /** @return array<string, mixed> */
    private function writeData(SwitchTemporalRule $rule): array
    {
        return ['name' => $rule->name, 'cycle' => $rule->cycle, 'interval' => $rule->interval, 'start_date' => $rule->start_date?->format('Y-m-d'), 'time_window_start' => $rule->time_window_start, 'time_window_stop' => $rule->time_window_stop, 'enabled' => $rule->enabled, 'days' => $rule->days ?? [], 'weekdays' => $rule->weekdays ?? [], 'month' => $rule->month, 'ordinal' => $rule->ordinal];
    }

    private function containsRule(mixed $node, string $id): bool
    {
        if (! is_array($node)) {
            return false;
        } $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        if (($node['module'] ?? null) === 'temporal_route' && in_array($id, is_array($data['rules'] ?? null) ? $data['rules'] : [], true)) {
            return true;
        } foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if ($this->containsRule($child, $id)) {
                return true;
            }
        }

return false;
    }
}
