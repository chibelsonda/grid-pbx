<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Services\StartServiceSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class DescendantOnboardingService
{
    public function __construct(
        private readonly SwitchAccountGateway $gateway,
        private readonly DescendantOnboardingReferenceService $references,
        private readonly AccountProjectionService $accountProjection,
        private readonly AccountHierarchyProjectionService $hierarchyProjection,
        private readonly AccountHierarchyService $hierarchy,
        private readonly AuditService $audit,
        private readonly StartServiceSyncService $serviceSync,
    ) {}

    /** @return array<string, mixed> */
    public function candidates(SwitchAccount $scope, User $actor): array
    {
        $this->assertReseller($scope);
        $scope->loadMissing('organization:organization_id,id,name');
        $descendants = $this->unmanagedDescendants($scope, $this->gateway->descendants($scope));
        $memberCount = $scope->organization->users()->count();
        $managedSwitchAccountIds = SwitchAccount::query()
            ->where('organization_id', $scope->organization_id)
            ->pluck('switch_account_id')
            ->flip();
        $candidates = [];
        $expiresAt = null;

        foreach ($descendants as $descendant) {
            $switchAccountId = $this->string($descendant['id'] ?? null);

            if ($switchAccountId === null) {
                continue;
            }

            $issued = $this->references->issue($actor, $scope, $switchAccountId);
            $expiresAt = $issued['expires_at'];
            $parentSwitchAccountId = $this->parentSwitchAccountId($descendant);
            $eligible = $parentSwitchAccountId === $scope->switch_account_id
                || ($parentSwitchAccountId !== null && $managedSwitchAccountIds->has($parentSwitchAccountId));
            $candidates[] = [
                'reference' => $issued['reference'],
                'name' => $this->string($descendant['name'] ?? null) ?? 'Unnamed Switch account',
                'realm' => $this->string($descendant['realm'] ?? null),
                'descendants_count' => is_numeric($descendant['descendants_count'] ?? null)
                    ? max(0, (int) $descendant['descendants_count'])
                    : 0,
                'eligible' => $eligible,
                'blocked_reason' => $eligible ? null : 'parent_not_projected',
            ];
        }

        usort($candidates, static fn (array $left, array $right): int => [
            mb_strtolower($left['name']),
            $left['realm'] ?? '',
        ] <=> [
            mb_strtolower($right['name']),
            $right['realm'] ?? '',
        ]);

        return [
            'candidates' => $candidates,
            'target_organization' => [
                'id' => $scope->organization->id,
                'name' => $scope->organization->name,
            ],
            'access_inheritance' => [
                'member_count' => $memberCount,
                'acknowledgement_required' => true,
            ],
            'reference_expires_at' => $expiresAt,
        ];
    }

    /** @return array<string, mixed> */
    public function onboard(
        SwitchAccount $scope,
        User $actor,
        string $reference,
        string $confirmation,
        ?string $ipAddress,
    ): array {
        $this->assertReseller($scope);
        $scope->loadMissing('organization:organization_id,id,name');
        $switchAccountId = $this->references->resolve($actor, $scope, $reference);
        $descendants = $this->gateway->descendants($scope);
        $candidate = collect($descendants)->first(
            fn (array $descendant): bool => ($descendant['id'] ?? null) === $switchAccountId,
        );

        if (! is_array($candidate)) {
            throw new ConflictHttpException('The selected account is no longer a descendant of this reseller. Refresh the candidate list.');
        }

        $candidateName = $this->string($candidate['name'] ?? null) ?? 'Unnamed Switch account';
        $parentSwitchAccountId = $this->parentSwitchAccountId($candidate);

        if ($parentSwitchAccountId !== $scope->switch_account_id
            && ($parentSwitchAccountId === null || ! SwitchAccount::query()
                ->where('organization_id', $scope->organization_id)
                ->where('switch_account_id', $parentSwitchAccountId)
                ->exists())) {
            throw new ConflictHttpException('Onboard the selected account parent before onboarding this descendant.');
        }

        if ($confirmation !== $candidateName) {
            throw ValidationException::withMessages([
                'confirmation' => 'Enter the descendant account name exactly as shown.',
            ]);
        }

        $snapshot = $this->gateway->findBySwitchAccountId($switchAccountId);
        $scopeSnapshot = $this->gateway->find($scope);
        $memberCount = $scope->organization->users()->count();
        $lock = Cache::lock('reseller-descendant-onboarding:'.hash('sha256', $switchAccountId), 15);

        /** @var array{account: SwitchAccount, response: array<string, mixed>} $onboarding */
        $onboarding = $lock->block(3, fn (): array => DB::transaction(function () use (
            $scope,
            $actor,
            $candidate,
            $candidateName,
            $snapshot,
            $scopeSnapshot,
            $descendants,
            $switchAccountId,
            $memberCount,
            $ipAddress,
        ): array {
            $scope = SwitchAccount::query()
                ->with('organization:organization_id,id,name')
                ->lockForUpdate()
                ->findOrFail($scope->getKey());

            if (SwitchAccount::query()->where('switch_account_id', $switchAccountId)->exists()) {
                throw new ConflictHttpException('The selected account is already managed by GridPBX. Refresh the candidate list.');
            }

            $account = new SwitchAccount;
            $account->fill([
                'organization_id' => $scope->organization_id,
                'switch_account_id' => $switchAccountId,
                'parent_switch_account_id' => $this->parentSwitchAccountId($candidate),
                'name' => $candidateName,
                'realm' => $this->string($candidate['realm'] ?? null),
                'is_enabled' => true,
                'descendants_count' => 0,
                'sync_status' => 'stale',
                'projection_version' => 0,
            ]);
            $account->save();
            $account = $this->accountProjection->project($account, $snapshot);
            $this->hierarchyProjection->project($account, $snapshot, []);
            $this->hierarchyProjection->project($scope, $scopeSnapshot, $descendants);

            $this->audit->record(
                $actor,
                $scope,
                'reseller_descendant.onboard',
                'succeeded',
                $account->id,
                [
                    'source_account_id' => $scope->id,
                    'target_organization_id' => $scope->organization->id,
                    'inherited_member_count' => $memberCount,
                    'existing_access_acknowledged' => true,
                ],
                $ipAddress,
                'switch_account',
            );

            return [
                'account' => $account,
                'response' => [
                    'onboarded_account' => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'realm' => $account->realm,
                        'enabled' => $account->is_enabled,
                    ],
                    'target_organization' => [
                        'id' => $scope->organization->id,
                        'name' => $scope->organization->name,
                    ],
                    'access_inheritance' => [
                        'member_count' => $memberCount,
                        'acknowledged' => true,
                    ],
                    'hierarchy' => $this->hierarchy->hierarchy($scope->refresh()),
                ],
            ];
        }));

        return [
            ...$onboarding['response'],
            'service_projection' => $this->startServiceProjection($onboarding['account'], $actor),
        ];
    }

    /** @return array{status: string, sync_run_id: ?string} */
    private function startServiceProjection(SwitchAccount $account, User $actor): array
    {
        try {
            $run = $this->serviceSync->handle($account, $actor);

            return [
                'status' => $run->status->value,
                'sync_run_id' => $run->id,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'status' => 'not_started',
                'sync_run_id' => null,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $descendants
     * @return list<array<string, mixed>>
     */
    private function unmanagedDescendants(SwitchAccount $scope, array $descendants): array
    {
        $switchAccountIds = collect($descendants)
            ->map(fn (array $descendant): ?string => $this->string($descendant['id'] ?? null))
            ->filter()
            ->values();
        $managed = SwitchAccount::query()
            ->whereIn('switch_account_id', $switchAccountIds)
            ->pluck('switch_account_id')
            ->flip();

        return collect($descendants)
            ->filter(function (array $descendant) use ($scope, $managed): bool {
                $switchAccountId = $this->string($descendant['id'] ?? null);

                return $switchAccountId !== null
                    && $switchAccountId !== $scope->switch_account_id
                    && ! $managed->has($switchAccountId);
            })
            ->values()
            ->all();
    }

    private function assertReseller(SwitchAccount $scope): void
    {
        if (! $scope->is_reseller) {
            throw new ConflictHttpException('Only reseller accounts can onboard Switch descendants.');
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function parentSwitchAccountId(array $snapshot): ?string
    {
        $parentId = $this->string($snapshot['parent_id'] ?? null);

        if ($parentId !== null) {
            return $parentId;
        }

        $tree = is_array($snapshot['tree'] ?? null) ? $snapshot['tree'] : [];

        for ($index = count($tree) - 1; $index >= 0; $index--) {
            $parentId = $this->string($tree[$index] ?? null);

            if ($parentId !== null) {
                return $parentId;
            }
        }

        return null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
