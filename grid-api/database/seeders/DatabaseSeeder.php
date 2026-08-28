<?php

namespace Database\Seeders;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'gridpbx'],
            ['name' => 'GridPBX'],
        );

        $user = User::query()->firstOrCreate([
            'email' => config('gridpbx.admin.email'),
        ], [
            'name' => config('gridpbx.admin.name'),
            'password' => config('gridpbx.admin.password'),
        ]);

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
}
