<?php

namespace Tests\Feature\Domains\Organizations;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationLogoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_account_administrator_can_store_stream_replace_and_remove_a_sanitized_logo(): void
    {
        Storage::fake('local');
        [$user, $organization, $account] = $this->accountMember('account_administrator');

        $response = $this->actingAs($user)->post(
            "/api/v1/accounts/{$account->id}/organization-logo",
            ['logo' => UploadedFile::fake()->image('brand.jpg', 1200, 300)],
            ['Accept' => 'application/json'],
        );

        $response->assertOk()
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.logo_available', true)
            ->assertJsonMissingPath('data.logo_path')
            ->assertJsonMissingPath('data.organization_id_internal');

        $organization->refresh();
        $firstPath = $organization->logo_path;
        $this->assertNotNull($firstPath);
        Storage::disk('local')->assertExists($firstPath);
        $stored = Storage::disk('local')->get($firstPath);
        $this->assertSame('image/png', (new \finfo(FILEINFO_MIME_TYPE))->buffer($stored));
        $this->assertSame([512, 128], array_slice(getimagesizefromstring($stored), 0, 2));

        $this->actingAs($user)
            ->get("/api/v1/accounts/{$account->id}/organization-logo")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertContent($stored);

        $this->actingAs($user)->post(
            "/api/v1/accounts/{$account->id}/organization-logo",
            ['logo' => UploadedFile::fake()->image('replacement.png', 200, 100)],
            ['Accept' => 'application/json'],
        )->assertOk();

        $organization->refresh();
        $this->assertNotSame($firstPath, $organization->logo_path);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($organization->logo_path);
        $replacementPath = $organization->logo_path;

        $this->actingAs($user)
            ->deleteJson("/api/v1/accounts/{$account->id}/organization-logo")
            ->assertOk()
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.logo_available', false)
            ->assertJsonPath('data.logo_updated_at', null)
            ->assertJsonMissingPath('data.logo_path');

        $organization->refresh();
        $this->assertNull($organization->logo_path);
        $this->assertNull($organization->logo_updated_at);
        Storage::disk('local')->assertMissing($replacementPath);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization.logo_updated',
            'resource_type' => 'organization',
            'resource_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization.logo_removed',
            'resource_type' => 'organization',
            'resource_id' => $organization->id,
        ]);
    }

    public function test_logo_access_is_account_scoped_and_mutations_require_settings_permission(): void
    {
        Storage::fake('local');
        [$operator, $organization, $account] = $this->accountMember('account_operator');
        $foreignAccount = SwitchAccount::factory()->create();

        $this->getJson("/api/v1/accounts/{$account->id}/organization-logo")
            ->assertUnauthorized();

        $this->actingAs($operator)->post(
            "/api/v1/accounts/{$account->id}/organization-logo",
            ['logo' => UploadedFile::fake()->image('brand.png', 64, 64)],
            ['Accept' => 'application/json'],
        )->assertForbidden();

        $this->actingAs($operator)
            ->deleteJson("/api/v1/accounts/{$account->id}/organization-logo")
            ->assertForbidden();
        $this->actingAs($operator)
            ->get("/api/v1/accounts/{$foreignAccount->id}/organization-logo")
            ->assertNotFound();

        $this->assertNull($organization->fresh()->logo_path);
        $this->assertDatabaseMissing('audit_logs', ['resource_type' => 'organization']);
    }

    public function test_logo_upload_rejects_unsafe_or_out_of_bounds_files_without_persisting_them(): void
    {
        Storage::fake('local');
        [$user, $organization, $account] = $this->accountMember('account_administrator');

        $this->actingAs($user)->post(
            "/api/v1/accounts/{$account->id}/organization-logo",
            ['logo' => UploadedFile::fake()->create('payload.svg', 1, 'image/svg+xml')],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors('logo');

        $this->actingAs($user)->post(
            "/api/v1/accounts/{$account->id}/organization-logo",
            ['logo' => UploadedFile::fake()->image('tiny.png', 16, 16)],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors('logo');

        $this->assertNull($organization->fresh()->logo_path);
        Storage::disk('local')->assertDirectoryEmpty('organization-branding');
        $this->assertDatabaseMissing('audit_logs', ['resource_type' => 'organization']);
    }

    public function test_account_resources_expose_only_safe_branding_metadata(): void
    {
        [$user, $organization, $account] = $this->accountMember('account_administrator');
        $organization->forceFill([
            'logo_path' => 'organization-branding/private/raw-path.png',
            'logo_updated_at' => now(),
        ])->save();

        $this->actingAs($user)->getJson('/api/v1/accounts')
            ->assertOk()
            ->assertJsonPath('data.0.organization.id', $organization->id)
            ->assertJsonPath('data.0.organization.branding.logo_available', true)
            ->assertJsonMissing(['logo_path' => 'organization-branding/private/raw-path.png'])
            ->assertJsonMissingPath('data.0.organization.organization_id');

        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('data.organization.id', $organization->id)
            ->assertJsonPath('data.organization.branding.logo_available', true)
            ->assertJsonMissing(['logo_path' => 'organization-branding/private/raw-path.png'])
            ->assertJsonMissingPath('data.organization.organization_id');
    }

    /** @return array{User, Organization, SwitchAccount} */
    private function accountMember(string $role): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role]);
        $account = SwitchAccount::factory()->for($organization)->create();

        return [$user, $organization, $account];
    }
}
