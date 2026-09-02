<?php

namespace Database\Seeders;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = $this->validatedAdminPassword();
        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'gridpbx'],
            ['name' => 'GridPBX'],
        );

        $user = User::query()->firstOrNew([
            'email' => config('gridpbx.admin.email'),
        ]);

        if (! $user->exists) {
            $user->fill([
                'name' => config('gridpbx.admin.name'),
                'password' => $adminPassword,
            ])->save();
        } elseif (Hash::check('admin-change-me', $user->password)) {
            $user->forceFill(['password' => $adminPassword])->save();
        }

        $organization->users()->syncWithoutDetaching([
            $user->getKey() => ['role' => 'platform_administrator'],
        ]);

        if ($accountId = config('gridpbx.switch_account.id')) {
            SwitchAccount::query()->updateOrCreate([
                'organization_id' => $organization->getKey(),
                'switch_account_id' => $accountId,
            ], [
                'name' => config('gridpbx.switch_account.name'),
                'realm' => config('gridpbx.switch_account.realm'),
                'timezone' => config('gridpbx.switch_account.timezone'),
                'is_enabled' => true,
            ]);
        }
    }

    private function validatedAdminPassword(): string
    {
        $password = config('gridpbx.admin.password');

        if (! is_string($password)
            || mb_strlen($password) < 12
            || in_array($password, ['admin-change-me', 'password', 'changeme'], true)) {
            throw new RuntimeException(
                'GRID_ADMIN_PASSWORD must be set to a non-default password of at least 12 characters before seeding.',
            );
        }

        return $password;
    }
}
