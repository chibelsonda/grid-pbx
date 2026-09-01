<?php

namespace App\Domains\Organizations\Services;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Eloquent\Collection;

class SwitchAccountService
{
    /** @return Collection<int, SwitchAccount> */
    public function accessibleTo(User $user): Collection
    {
        $accounts = SwitchAccount::query()
            ->whereHas('organization.users', fn ($query) => $query->whereKey($user->getKey()))
            ->with('organization:organization_id,id,name,logo_path,logo_updated_at')
            ->orderByDesc('is_enabled')
            ->orderBy('name')
            ->get();
        $rolesByOrganization = $user->organizations()
            ->pluck('organization_user.role', 'organizations.organization_id');

        $accounts->each(function (SwitchAccount $account) use ($rolesByOrganization): void {
            $account->setAttribute(
                'organization_role',
                $rolesByOrganization->get($account->organization_id),
            );
        });

        return $accounts;
    }

    public function findAccessible(User $user, string $accountId): SwitchAccount
    {
        return SwitchAccount::query()
            ->where('id', $accountId)
            ->where('is_enabled', true)
            ->whereHas('organization.users', fn ($query) => $query->whereKey($user->getKey()))
            ->firstOrFail();
    }

    public function findMemberAccessible(User $user, string $accountId): SwitchAccount
    {
        return SwitchAccount::query()
            ->where('id', $accountId)
            ->whereHas('organization.users', fn ($query) => $query->whereKey($user->getKey()))
            ->firstOrFail();
    }

    public function findDetailedAccessible(User $user, string $accountId): SwitchAccount
    {
        $account = $this->findMemberAccessible($user, $accountId);

        $account
            ->load([
                'organization:organization_id,id,name,logo_path,logo_updated_at',
                'phoneNumbers' => fn ($query) => $query
                    ->select([
                        'phone_number_id',
                        'id',
                        'switch_account_id',
                        'number',
                        'features',
                        'cnam_display_name',
                        'e911_status',
                    ])
                    ->orderBy('number')
                    ->orderBy('phone_number_id'),
                'callflows' => fn ($query) => $query
                    ->select(['callflow_id', 'id', 'switch_account_id', 'switch_resource_id', 'name'])
                    ->orderBy('name')
                    ->orderBy('callflow_id'),
            ])
            ->loadCount([
                'extensions',
                'devices',
                'phoneNumbers',
                'callflows',
                'voicemailBoxes',
                'queues',
                'media',
                'recordings',
            ]);

        $account->setAttribute(
            'organization_role',
            $user->organizations()
                ->whereKey($account->organization_id)
                ->value('organization_user.role'),
        );

        return $account;
    }
}
