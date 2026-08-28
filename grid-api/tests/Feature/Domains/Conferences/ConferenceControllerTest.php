<?php

namespace Tests\Feature\Domains\Conferences;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Conferences\Contracts\SwitchConferenceGateway;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConferenceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_creates_conference_with_owner_numbers_and_write_only_pins(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $owner = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1', 'display_name' => 'Ada Lovelace', 'extension' => '1001']);
        $gateway = $this->mock(SwitchConferenceGateway::class);
        $gateway->shouldReceive('create')->once()->withArgs(fn (SwitchAccount $received, array $data): bool => $received->is($account)
            && $data['switch_owner_reference'] === 'switch-user-1' && $data['member_pin'] === '1234')
            ->andReturn($this->snapshot(['owner_id' => 'switch-user-1', 'member' => ['numbers' => ['7001'], 'pins' => ['1234'], 'join_muted' => true], 'moderator' => ['numbers' => ['7099'], 'pins' => ['9876']]]));

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/conferences", [
            ...$this->payload(), 'owner_id' => $owner->id, 'member_pin' => '1234', 'moderator_pin' => '9876',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Daily standup')->assertJsonPath('data.owner.id', $owner->id)
            ->assertJsonPath('data.member_numbers.0', '7001')->assertJsonPath('data.member_pin_configured', true)
            ->assertJsonMissingPath('data.member_pin')->assertJsonMissingPath('data.conference_id')
            ->assertJsonMissingPath('data.switch_resource_id')->assertJsonMissingPath('data.switch_json');
        $conference = SwitchConference::query()->where('id', $response->json('data.id'))->firstOrFail();
        $this->assertSame('[REDACTED]', $conference->switch_json['member']['pins']);
        $this->assertDatabaseHas('switch_conference_numbers', ['switch_conference_id' => $conference->getKey(), 'role' => 'moderator', 'number' => '7099']);
    }

    public function test_read_only_user_cannot_mutate_and_cross_tenant_owner_is_rejected(): void
    {
        $this->mock(SwitchConferenceGateway::class)->shouldNotReceive('create');
        [$readOnly, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->actingAs($readOnly)->postJson("/api/v1/accounts/{$account->id}/conferences", $this->payload())->assertForbidden();

        [$operator, $managed] = $this->accessibleAccount();
        $foreignOwner = SwitchExtension::factory()->create();
        $this->actingAs($operator)->postJson("/api/v1/accounts/{$managed->id}/conferences", [...$this->payload(), 'owner_id' => $foreignOwner->id])
            ->assertUnprocessable()->assertJsonValidationErrors('owner_id');
    }

    public function test_delete_is_blocked_when_a_callflow_references_the_conference(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $conference = SwitchConference::factory()->for($account)->create(['switch_resource_id' => 'switch-conference-1']);
        SwitchCallflow::factory()->for($account)->create(['switch_json' => ['flow' => ['module' => 'conference', 'data' => ['id' => 'switch-conference-1'], 'children' => []]]]);
        $this->mock(SwitchConferenceGateway::class)->shouldNotReceive('delete');

        $this->actingAs($user)->deleteJson("/api/v1/accounts/{$account->id}/conferences/{$conference->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('conference');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'name' => 'Daily standup', 'owner_id' => null, 'conference_numbers' => ['7000'],
            'member_numbers' => ['7001'], 'moderator_numbers' => ['7099'], 'member_pin' => null,
            'clear_member_pin' => false, 'moderator_pin' => null, 'clear_moderator_pin' => false,
            'member_join_muted' => true, 'member_join_deaf' => false, 'member_play_entry_prompt' => false,
            'moderator_join_muted' => false, 'moderator_join_deaf' => false, 'max_participants' => 50,
            'language' => 'en-US', 'profile_name' => null, 'caller_controls' => null, 'moderator_controls' => null,
            'play_name' => false, 'play_welcome' => true, 'require_moderator' => true, 'wait_for_moderator' => true,
        ];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function snapshot(array $overrides = []): array
    {
        return [
            'id' => 'switch-conference-1', 'name' => 'Daily standup', 'conference_numbers' => ['7000'],
            'member' => ['numbers' => ['7001']], 'moderator' => ['numbers' => ['7099']],
            'max_participants' => 50, 'require_moderator' => true, 'wait_for_moderator' => true,
            '_read_only' => ['members' => 0, 'moderators' => 0, 'duration' => 0, 'is_locked' => false], ...$overrides,
        ];
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(OrganizationRole $role = OrganizationRole::AccountOperator): array
    {
        $user = User::factory()->create(); $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);
        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
