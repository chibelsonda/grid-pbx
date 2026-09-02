<?php

namespace Tests\Feature;

use App\Domains\IdentityAccess\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederSecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string|null}> */
    public static function unsafePasswords(): array
    {
        return [
            'missing' => [null],
            'known default' => ['admin-change-me'],
            'too short' => ['short'],
        ];
    }

    #[DataProvider('unsafePasswords')]
    public function test_seeding_rejects_missing_weak_or_default_admin_password(?string $password): void
    {
        config(['gridpbx.admin.password' => $password]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GRID_ADMIN_PASSWORD must be set');

        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeding_creates_platform_administrator_with_a_strong_configured_password(): void
    {
        config([
            'gridpbx.admin.email' => 'secure-admin@example.test',
            'gridpbx.admin.password' => 'a-strong-bootstrap-password',
        ]);

        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'secure-admin@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('a-strong-bootstrap-password', $user->password));
        $this->assertDatabaseHas('organization_user', [
            'user_id' => $user->getKey(),
            'role' => 'platform_administrator',
        ]);
    }

    public function test_seeding_rotates_the_known_legacy_default_without_overwriting_other_passwords(): void
    {
        $legacyAdmin = User::factory()->create([
            'email' => 'legacy-admin@example.test',
            'password' => 'admin-change-me',
        ]);
        $existingAdmin = User::factory()->create([
            'email' => 'existing-admin@example.test',
            'password' => 'existing-secure-password',
        ]);

        config([
            'gridpbx.admin.email' => $legacyAdmin->email,
            'gridpbx.admin.password' => 'new-strong-bootstrap-password',
        ]);
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Hash::check(
            'new-strong-bootstrap-password',
            $legacyAdmin->fresh()->password,
        ));

        config([
            'gridpbx.admin.email' => $existingAdmin->email,
            'gridpbx.admin.password' => 'another-strong-bootstrap-password',
        ]);
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Hash::check(
            'existing-secure-password',
            $existingAdmin->fresh()->password,
        ));
    }
}
