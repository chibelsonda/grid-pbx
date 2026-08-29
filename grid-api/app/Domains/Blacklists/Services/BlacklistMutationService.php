<?php

namespace App\Domains\Blacklists\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Blacklists\Contracts\SwitchBlacklistGateway;
use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BlacklistMutationService
{
    public function __construct(private readonly SwitchBlacklistGateway $gateway, private readonly BlacklistProjectionService $projection, private readonly AuditService $audit) {}

    public function create(SwitchAccount $account, User $actor, array $data, ?string $ip = null): SwitchBlacklist
    {
        $resourceId = null;
        $beforeActive = $this->gateway->activeIds($account);
        try {
            $snapshot = $this->gateway->create($account, [...$data, 'switch_flags' => []]);
            $resourceId = is_string($snapshot['id'] ?? null) ? $snapshot['id'] : null;
            if ($resourceId === null) {
                throw new \UnexpectedValueException('Switch blacklist create response is missing its identifier.');
            }
            $active = (bool) ($data['is_active'] ?? false);
            if ($active) {
                $this->gateway->setActiveIds($account, array_values(array_unique([...$beforeActive, $resourceId])));
            }

            return DB::transaction(function () use ($account, $actor, $ip, $snapshot, $active) {
                $model = $this->projection->project($account, $snapshot, $active);
                $this->audit->record($actor, $account, 'blacklist.created', 'succeeded', $model->switch_resource_id, [], $ip, 'blacklist');

                return $model;
            });
        } catch (Throwable $e) {
            if ($resourceId !== null) {
                try {
                    $this->gateway->setActiveIds($account, $beforeActive);
                    $this->gateway->delete($account, $resourceId);
                } catch (Throwable) {
                }
            }
            throw $e;
        }
    }

    public function update(SwitchAccount $account, SwitchBlacklist $blacklist, User $actor, array $data, ?string $ip = null): SwitchBlacklist
    {
        $beforeActive = $this->gateway->activeIds($account);
        $switchFlags = $this->stringList($blacklist->switch_json['flags'] ?? $blacklist->flags);
        $previous = ['name' => $blacklist->name, 'numbers' => $blacklist->entries->pluck('number')->all(), 'should_block_anonymous' => $blacklist->should_block_anonymous, 'switch_flags' => $switchFlags];
        try {
            $snapshot = $this->gateway->update($account, $blacklist->switch_resource_id, [...$data, 'switch_flags' => $switchFlags]);
            $active = (bool) $data['is_active'];
            $after = array_values(array_filter($beforeActive, fn ($id) => $id !== $blacklist->switch_resource_id));
            if ($active) {
                $after[] = $blacklist->switch_resource_id;
            }
            if ($after !== $beforeActive) {
                $this->gateway->setActiveIds($account, $after);
            }

            return DB::transaction(function () use ($account, $actor, $ip, $snapshot, $active) {
                $model = $this->projection->project($account, $snapshot, $active);
                $this->audit->record($actor, $account, 'blacklist.updated', 'succeeded', $model->switch_resource_id, [], $ip, 'blacklist');

                return $model;
            });
        } catch (Throwable $e) {
            try {
                $this->gateway->update($account, $blacklist->switch_resource_id, $previous);
                $this->gateway->setActiveIds($account, $beforeActive);
            } catch (Throwable) {
            } throw $e;
        }
    }

    public function delete(SwitchAccount $account, SwitchBlacklist $blacklist, User $actor, ?string $ip = null): void
    {
        if ($blacklist->is_active) {
            throw ValidationException::withMessages(['blacklist' => ['Deactivate this blacklist before deleting it.']]);
        }
        $this->gateway->delete($account, $blacklist->switch_resource_id);
        DB::transaction(function () use ($account, $actor, $blacklist, $ip): void {
            $blacklist->delete();
            $this->audit->record($actor, $account, 'blacklist.deleted', 'succeeded', $blacklist->switch_resource_id, [], $ip, 'blacklist');
        });
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? $value : [],
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }
}
