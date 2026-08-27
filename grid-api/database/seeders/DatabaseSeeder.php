<?php

namespace Database\Seeders;

use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use App\Domains\Organizations\Infrastructure\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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

        if ($accountId = config('gridpbx.kazoo_account.id')) {
            KazooAccount::query()->updateOrCreate([
                'organization_id' => $organization->getKey(),
                'kazoo_account_id' => $accountId,
            ], [
                'name' => config('gridpbx.kazoo_account.name'),
                'realm' => config('gridpbx.kazoo_account.realm'),
                'is_enabled' => true,
            ]);
        }
    }
}
