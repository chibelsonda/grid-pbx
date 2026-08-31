<?php

namespace App\Domains\TemporalRouting\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Contracts\SwitchTemporalRuleSetGateway;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TemporalRuleSetMutationService
{
    public function __construct(private readonly SwitchTemporalRuleSetGateway $gateway, private readonly TemporalRuleSetProjectionService $projection, private readonly TemporalRuleStatusService $status, private readonly AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(SwitchAccount $account, User $actor, array $data, ?string $ip = null): SwitchTemporalRuleSet
    {
        $resolved = $this->resolve($account, $data);
        $resourceId = null;
        try {
            $snapshot = $this->gateway->create($account, $resolved);
            $resourceId = is_string($snapshot['id'] ?? null) ? $snapshot['id'] : null;
            if ($resourceId === null) {
                throw new \UnexpectedValueException('Switch temporal rule set create response is missing its identifier.');
            }

            return DB::transaction(function () use ($account, $actor, $ip, $snapshot) {
                $set = $this->projection->project($account, $snapshot);
                $set->setAttribute('effective_status', $this->status->ruleSet($account, $set));
                $this->audit->record($actor, $account, 'temporal_rule_set.created', 'succeeded', $set->switch_resource_id, [], $ip, 'temporal_rule_set');

                return $set;
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
    public function update(SwitchAccount $account, SwitchTemporalRuleSet $set, User $actor, array $data, ?string $ip = null): SwitchTemporalRuleSet
    {
        $resolved = $this->resolve($account, $data);
        $previous = ['name' => $set->name, 'switch_rule_ids' => $set->rules()->orderBy('position')->pluck('switch_rule_resource_id')->all(), 'flags' => $this->flags($set->switch_json)];
        try {
            $snapshot = $this->gateway->update($account, $set->switch_resource_id, $resolved);

            return DB::transaction(function () use ($account, $actor, $ip, $snapshot) {
                $updated = $this->projection->project($account, $snapshot);
                $updated->setAttribute('effective_status', $this->status->ruleSet($account, $updated));
                $this->audit->record($actor, $account, 'temporal_rule_set.updated', 'succeeded', $updated->switch_resource_id, [], $ip, 'temporal_rule_set');

                return $updated;
            });
        } catch (Throwable $e) {
            try {
                $this->gateway->update($account, $set->switch_resource_id, $previous);
            } catch (Throwable) {
            } throw $e;
        }
    }

    public function delete(SwitchAccount $account, SwitchTemporalRuleSet $set, User $actor, ?string $ip = null): void
    {
        foreach ($account->callflows()->get() as $callflow) {
            if ($this->containsSet($callflow->switch_json['flow'] ?? null, $set->switch_resource_id)) {
                throw ValidationException::withMessages(['rule_set' => ['Remove this rule set from call routing before deleting it.']]);
            }
        }
        $this->gateway->delete($account, $set->switch_resource_id);
        DB::transaction(function () use ($account, $actor, $set, $ip): void {
            $set->rules()->delete();
            $set->delete();
            $this->audit->record($actor, $account, 'temporal_rule_set.deleted', 'succeeded', $set->switch_resource_id, [], $ip, 'temporal_rule_set');
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function resolve(SwitchAccount $account, array $data): array
    {
        $ids = array_values($data['rule_ids']);
        $rules = $account->temporalRules()->whereIn('id', $ids)->get()->keyBy('id');
        if ($rules->count() !== count($ids)) {
            throw ValidationException::withMessages(['rule_ids' => ['One or more selected temporal rules are unavailable for this account.']]);
        }

        return [...$data, 'switch_rule_ids' => array_map(fn ($id) => $rules[$id]->switch_resource_id, $ids)];
    }

    /** @param array<string, mixed>|null $snapshot @return list<string> */
    private function flags(?array $snapshot): array
    {
        $flags = $snapshot['flags'] ?? [];

        return is_array($flags)
            ? array_values(array_filter($flags, static fn (mixed $flag): bool => is_string($flag)))
            : [];
    }

    private function containsSet(mixed $node, string $id): bool
    {
        if (! is_array($node)) {
            return false;
        } $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        if (($node['module'] ?? null) === 'temporal_route' && ($data['rule_set'] ?? null) === $id) {
            return true;
        } foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if ($this->containsSet($child, $id)) {
                return true;
            }
        }

        return false;
    }
}
