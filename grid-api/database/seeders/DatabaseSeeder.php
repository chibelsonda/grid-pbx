<?php

namespace Database\Seeders;

use App\Models\KazooAccount;
use App\Models\Organization;
use App\Models\User;
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
            'email' => env('GRID_ADMIN_EMAIL', 'admin@gridpbx.local'),
        ], [
            'name' => 'Grid Admin',
            'password' => env('GRID_ADMIN_PASSWORD', 'admin-change-me'),
        ]);

        $organization->users()->syncWithoutDetaching([
            $user->getKey() => ['role' => 'platform_administrator'],
        ]);

        if ($accountId = env('KAZOO_ACCOUNT_ID')) {
            KazooAccount::query()->updateOrCreate([
                'organization_id' => $organization->getKey(),
                'kazoo_account_id' => $accountId,
            ], [
                'name' => env('KAZOO_ACCOUNT_NAME', 'GridPBX'),
                'realm' => env('KAZOO_ACCOUNT_REALM', 'gridpbx.local'),
                'is_enabled' => true,
            ]);
        }
    }
}
