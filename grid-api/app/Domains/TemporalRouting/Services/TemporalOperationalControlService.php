<?php

namespace App\Domains\TemporalRouting\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleGateway;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TemporalOperationalControlService
{
    public function __construct(
        private readonly SwitchTemporalRuleGateway $gateway,
        private readonly TemporalRuleProjectionService $projection,
        private readonly TemporalRuleStatusService $status,
        private readonly AuditService $audit,
    ) {}

    public function controlRule(SwitchAccount $account, SwitchTemporalRule $rule, User $actor, string $action, ?string $ip = null): SwitchTemporalRule
    {
        return Cache::lock("temporal-controls:{$account->getKey()}", 15)->block(5, function () use ($account, $rule, $actor, $action, $ip): SwitchTemporalRule {
            try {
                $snapshot = $this->gateway->setOverride($account, $rule->switch_resource_id, $this->override($action));

                return DB::transaction(function () use ($account, $actor, $action, $ip, $snapshot): SwitchTemporalRule {
                    $projected = $this->projection->project($account, $snapshot);
                    $projected->setAttribute('effective_status', $this->status->rule($account, $projected));
                    $this->audit->record($actor, $account, "temporal_rule.{$action}", 'succeeded', $projected->switch_resource_id, [
                        'override' => $projected->enabled,
                    ], $ip, 'temporal_rule');

                    return $projected;
                });
            } catch (Throwable $exception) {
                $this->audit->record($actor, $account, "temporal_rule.{$action}", 'failed', $rule->switch_resource_id, [
                    'error_type' => $exception::class,
                ], $ip, 'temporal_rule');

                throw $exception;
            }
        });
    }

    public function controlRuleSet(SwitchAccount $account, SwitchTemporalRuleSet $set, User $actor, string $action, ?string $ip = null): SwitchTemporalRuleSet
    {
        return Cache::lock("temporal-controls:{$account->getKey()}", 15)->block(5, function () use ($account, $set, $actor, $action, $ip): SwitchTemporalRuleSet {
            $set->load('rules.rule');
            $rules = $set->rules->pluck('rule')->filter()->values();

            if ($rules->count() !== $set->rules->count()) {
                throw ValidationException::withMessages(['rule_set' => ['Synchronize temporal routing before controlling a rule set with unresolved members.']]);
            }

            if ($rules->isEmpty()) {
                throw ValidationException::withMessages(['rule_set' => ['Add at least one resolved rule before using operational controls.']]);
            }

            $completed = [];
            $snapshots = [];
            $compensationFailures = [];

            try {
                foreach ($rules as $rule) {
                    $snapshots[] = $this->gateway->setOverride($account, $rule->switch_resource_id, $this->override($action));
                    $completed[] = ['rule' => $rule, 'previous' => $rule->enabled];
                }

                return DB::transaction(function () use ($account, $set, $actor, $action, $ip, $snapshots): SwitchTemporalRuleSet {
                    foreach ($snapshots as $snapshot) {
                        $this->projection->project($account, $snapshot);
                    }

                    $refreshed = $set->fresh(['rules.rule']);
                    $refreshed->setAttribute('effective_status', $this->status->ruleSet($account, $refreshed));
                    $this->audit->record($actor, $account, "temporal_rule_set.{$action}", 'succeeded', $set->switch_resource_id, [
                        'rule_count' => count($snapshots),
                    ], $ip, 'temporal_rule_set');

                    return $refreshed;
                });
            } catch (Throwable $exception) {
                foreach (array_reverse($completed) as $operation) {
                    try {
                        $this->gateway->setOverride($account, $operation['rule']->switch_resource_id, $operation['previous']);
                    } catch (Throwable) {
                        $compensationFailures[] = $operation['rule']->id;
                    }
                }

                $this->audit->record($actor, $account, "temporal_rule_set.{$action}", 'failed', $set->switch_resource_id, [
                    'error_type' => $exception::class,
                    'compensation_failure_count' => count($compensationFailures),
                    'requires_sync' => $compensationFailures !== [],
                ], $ip, 'temporal_rule_set');

                throw $exception;
            }
        });
    }

    private function override(string $action): ?bool
    {
        return match ($action) {
            'enable' => true,
            'disable' => false,
            'reset' => null,
            default => throw ValidationException::withMessages(['action' => ['Unsupported temporal control action.']]),
        };
    }
}
