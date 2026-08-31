<?php

namespace Tests\Feature\Domains\Faxes;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Faxes\Contracts\SwitchFaxBoxGateway;
use App\Domains\Faxes\Contracts\SwitchFaxGateway;
use App\Domains\Faxes\Models\SwitchFax;
use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use GridPbx\Switch\Shared\Http\BinaryResponse;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FaxControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_creates_fax_box_with_public_owner_and_safe_response(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $owner = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $this->mock(SwitchFaxBoxGateway::class)->shouldReceive('create')->once()->withArgs(fn (SwitchAccount $received, array $data): bool => $received->is($account) && $data['switch_owner_reference'] === 'switch-user-1')->andReturn($this->boxSnapshot(['owner_id' => 'switch-user-1', 'smtp_email_address' => 'auto@fax.test']));
        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/fax-boxes", [...$this->boxPayload(), 'owner_id' => $owner->id]);
        $response->assertCreated()->assertJsonPath('data.name', 'Main fax')->assertJsonPath('data.owner.id', $owner->id)->assertJsonPath('data.smtp_email_address', 'auto@fax.test')->assertJsonMissingPath('data.fax_box_id')->assertJsonMissingPath('data.switch_resource_id')->assertJsonMissingPath('data.switch_json');
    }

    public function test_read_only_user_cannot_create_and_cross_tenant_owner_is_rejected(): void
    {
        $this->mock(SwitchFaxBoxGateway::class)->shouldNotReceive('create');
        [$readOnly, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->actingAs($readOnly)->postJson("/api/v1/accounts/{$account->id}/fax-boxes", $this->boxPayload())->assertForbidden();
        [$operator, $managed] = $this->accessibleAccount();
        $foreignOwner = SwitchExtension::factory()->create();
        $this->actingAs($operator)->postJson("/api/v1/accounts/{$managed->id}/fax-boxes", [...$this->boxPayload(), 'owner_id' => $foreignOwner->id])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
    }

    public function test_options_expose_public_owner_choices_phone_numbers_and_supported_timezones(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $account->update(['timezone' => 'Asia/Manila']);
        SwitchPhoneNumber::factory()->for($account)->create(['number' => '+12025550100']);

        $response = $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/fax-boxes/options");

        $response->assertOk()
            ->assertJsonPath('data.account_defaults.timezone', 'Asia/Manila')
            ->assertJsonPath('data.caller_id_numbers.0', '+12025550100');
        $this->assertContains('Asia/Manila', $response->json('data.timezones'));
    }

    public function test_update_preserves_hidden_notification_channels_and_external_flags(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $callback = ['url' => 'https://hooks.example.test/fax', 'method' => 'post', 'type' => 'json'];
        $sms = ['send_to' => ['+12025550199']];
        $box = SwitchFaxBox::factory()->for($account)->create([
            'switch_resource_id' => 'switch-box-1',
            'switch_json' => [
                'flags' => ['external-policy'],
                'notifications' => [
                    'inbound' => ['callback' => $callback, 'sms' => $sms],
                    'outbound' => ['callback' => $callback],
                ],
            ],
        ]);
        $gateway = $this->mock(SwitchFaxBoxGateway::class);
        $gateway->shouldReceive('update')->once()->withArgs(
            fn (SwitchAccount $received, string $resourceId, array $data): bool => $received->is($account)
                && $resourceId === 'switch-box-1'
                && $data['switch_flags'] === ['external-policy']
                && $data['switch_inbound_notification_extras'] === ['callback' => $callback, 'sms' => $sms]
                && $data['switch_outbound_notification_extras'] === ['callback' => $callback],
        )->andReturn($this->boxSnapshot([
            'flags' => ['external-policy'],
            'notifications' => [
                'inbound' => ['callback' => $callback, 'sms' => $sms, 'email' => ['send_to' => ['ops@example.test']]],
                'outbound' => ['callback' => $callback, 'email' => ['send_to' => ['ops@example.test']]],
            ],
        ]));

        $response = $this->actingAs($user)->putJson(
            "/api/v1/accounts/{$account->id}/fax-boxes/{$box->id}",
            $this->boxPayload(),
        );

        $response->assertOk()
            ->assertJsonMissingPath('data.flags')
            ->assertJsonMissingPath('data.notifications');
        $box->refresh();
        $this->assertSame(['external-policy'], $box->switch_json['flags']);
        $this->assertSame($callback, $box->switch_json['notifications']['inbound']['callback']);
        $this->assertSame($sms, $box->switch_json['notifications']['inbound']['sms']);
    }

    public function test_operator_cannot_submit_hidden_switch_owned_fax_fields(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $this->mock(SwitchFaxBoxGateway::class)->shouldIgnoreMissing();

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/fax-boxes", [
                ...$this->boxPayload(),
                'flags' => ['operator-controlled'],
                'notifications' => ['inbound' => []],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['flags', 'notifications']);
    }

    public function test_fax_box_delete_is_blocked_by_callflow_reference(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $box = SwitchFaxBox::factory()->for($account)->create(['switch_resource_id' => 'switch-box-1']);
        SwitchCallflow::factory()->for($account)->create(['switch_json' => ['flow' => ['module' => 'faxbox', 'data' => ['faxbox_id' => 'switch-box-1'], 'children' => []]]]);
        $this->mock(SwitchFaxBoxGateway::class)->shouldNotReceive('delete');
        $this->actingAs($user)->deleteJson("/api/v1/accounts/{$account->id}/fax-boxes/{$box->id}")->assertUnprocessable()->assertJsonValidationErrors('fax_box');
    }

    public function test_authorized_user_streams_projected_fax_document_without_internal_ids(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $fax = SwitchFax::factory()->for($account)->create(['switch_resource_id' => '202608-fax-1', 'folder' => 'inbox', 'has_document' => true]);
        $this->mock(SwitchFaxGateway::class)->shouldReceive('document')->once()->andReturn(new BinaryResponse(Utils::streamFor('%PDF'), 200, 'application/pdf', 4, null));
        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/faxes/{$fax->id}")->assertOk()->assertJsonPath('data.id', $fax->id)->assertJsonMissingPath('data.fax_id')->assertJsonMissingPath('data.switch_resource_id')->assertJsonMissingPath('data.switch_json');
        $response = $this->actingAs($user)->get("/api/v1/accounts/{$account->id}/faxes/{$fax->id}/document?download=1");
        $response->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('cache-control', 'no-store, private');
        $this->assertSame('%PDF', $response->streamedContent());
    }

    public function test_fax_history_exposes_safe_disabled_operation_capabilities(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/faxes");

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('capabilities.send.switch_supported', true)
            ->assertJsonPath('capabilities.send.enabled', false)
            ->assertJsonPath('capabilities.forward.enabled', false)
            ->assertJsonPath('capabilities.resubmit.enabled', false)
            ->assertJsonPath('capabilities.delete_message.enabled', false)
            ->assertJsonPath('capabilities.delete_document.enabled', false)
            ->assertJsonMissingPath('capabilities.send.url')
            ->assertJsonMissingPath('capabilities.send.switch_resource_id');
    }

    private function boxPayload(): array
    {
        return ['name' => 'Main fax', 'owner_id' => null, 'caller_id' => '+12025550100', 'caller_name' => 'Main fax', 'fax_header' => 'GridPBX', 'fax_identity' => '+12025550100', 'fax_timezone' => 'UTC', 'retries' => 2, 't38_enabled' => true, 'custom_smtp_email_address' => null, 'smtp_permission_list' => ['.*@example\\.test'], 'inbound_notification_emails' => ['ops@example.test'], 'outbound_notification_emails' => ['ops@example.test']];
    }

    private function boxSnapshot(array $overrides = []): array
    {
        return ['id' => 'switch-box-1', 'name' => 'Main fax', 'caller_id' => '+12025550100', 'retries' => 2, 'media' => ['fax_option' => true], 'notifications' => ['inbound' => ['email' => ['send_to' => ['ops@example.test']]], 'outbound' => ['email' => ['send_to' => ['ops@example.test']]]], ...$overrides];
    }

    private function accessibleAccount(OrganizationRole $role = OrganizationRole::AccountOperator): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
