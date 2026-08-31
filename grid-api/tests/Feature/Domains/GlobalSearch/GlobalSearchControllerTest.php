<?php

namespace Tests\Feature\Domains\GlobalSearch;

use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\Recordings\Models\SwitchRecording;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GlobalSearchControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_searches_account_projections_with_a_stable_safe_contract(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $secret = 'switch-secret-resource-id';
        $extension = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Alice Operator',
            'extension' => '2101',
            'email' => 'alice@example.test',
            'switch_resource_id' => $secret,
            'switch_json' => ['sip' => ['password' => 'never-return-this']],
        ]);
        SwitchDevice::factory()->for($account)->create(['name' => 'Alice desk phone']);
        SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+15552101000',
            'cnam_display_name' => 'Alice Support',
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'name' => 'Alice main route',
            'numbers' => ['2101'],
        ]);
        SwitchVoicemailBox::factory()->for($account)->create(['name' => 'Alice mailbox']);
        SwitchQueue::factory()->for($account)->create(['name' => 'Alice support queue']);
        SwitchMenu::factory()->for($account)->create(['name' => 'Alice IVR']);
        SwitchConference::factory()->for($account)->create(['name' => 'Alice conference']);
        SwitchDirectory::factory()->for($account)->create(['name' => 'Alice directory']);
        SwitchGroup::factory()->for($account)->create(['name' => 'Alice sales group']);
        SwitchMedia::factory()->for($account)->create(['name' => 'Alice hold music']);
        SwitchRecording::factory()->for($account)->create([
            'name' => 'Alice support recording',
            'direction' => 'outbound',
        ]);
        SwitchFaxBox::factory()->for($account)->create(['name' => 'Alice fax box']);
        SwitchBlacklist::factory()->for($account)->create([
            'name' => 'Alice blocked callers',
            'is_active' => true,
        ]);
        SwitchCallerIdList::query()->create([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => 'alice-caller-id-list',
            'name' => 'Alice trusted callers',
            'sync_status' => 'healthy',
            'projection_version' => 1,
            'switch_json' => [],
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/search?q=alice",
        );

        $response->assertOk()
            ->assertJsonPath('data.query', 'alice')
            ->assertJsonPath('data.total', 15)
            ->assertJsonCount(15, 'data.groups')
            ->assertJsonPath('data.groups.0.type', 'extension')
            ->assertJsonPath('data.groups.0.label', 'People & Extensions')
            ->assertJsonPath('data.groups.0.results.0.id', $extension->id)
            ->assertJsonPath('data.groups.0.results.0.title', 'Alice Operator')
            ->assertJsonPath('data.groups.0.results.0.subtitle', '2101 · alice@example.test')
            ->assertJsonPath('data.groups.0.results.0.matched_field', 'display_name')
            ->assertJsonPath('data.groups.10.type', 'media')
            ->assertJsonPath('data.groups.10.results.0.title', 'Alice hold music')
            ->assertJsonPath('data.groups.11.type', 'recording')
            ->assertJsonPath('data.groups.11.results.0.title', 'Alice support recording')
            ->assertJsonPath('data.groups.11.results.0.subtitle', 'outbound · +15550001000 · +15550002000')
            ->assertJsonPath('data.groups.12.type', 'fax_box')
            ->assertJsonPath('data.groups.12.results.0.title', 'Alice fax box')
            ->assertJsonPath('data.groups.13.type', 'blacklist')
            ->assertJsonPath('data.groups.13.results.0.title', 'Alice blocked callers')
            ->assertJsonPath('data.groups.13.results.0.subtitle', 'Active blacklist')
            ->assertJsonPath('data.groups.14.type', 'caller_id_list')
            ->assertJsonPath('data.groups.14.results.0.title', 'Alice trusted callers')
            ->assertJsonMissingPath('data.groups.0.results.0.extension_id')
            ->assertJsonMissingPath('data.groups.0.results.0.switch_resource_id')
            ->assertJsonMissingPath('data.groups.0.results.0.switch_json')
            ->assertDontSee($secret)
            ->assertDontSee('never-return-this');
    }

    public function test_exact_matches_rank_before_prefix_and_contains_matches(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $exact = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Zed Operator',
            'username' => 'alice',
        ]);
        $prefix = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Alice Operator',
            'username' => 'operator.alice',
        ]);
        $contains = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Support for Alice',
            'username' => 'support.alice',
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/search?q=alice&types[]=extension",
        );

        $response->assertOk()
            ->assertJsonPath('data.groups.0.results.0.id', $exact->id)
            ->assertJsonPath('data.groups.0.results.1.id', $prefix->id)
            ->assertJsonPath('data.groups.0.results.2.id', $contains->id);
    }

    public function test_exact_match_is_not_hidden_by_the_candidate_limit(): void
    {
        [$user, $account] = $this->accessibleAccount();
        SwitchExtension::factory()->for($account)->count(26)->sequence(
            fn ($sequence): array => [
                'display_name' => sprintf('Candidate Alice %02d', $sequence->index + 1),
                'first_name' => 'Candidate',
                'last_name' => sprintf('Person %02d', $sequence->index + 1),
                'username' => sprintf('candidate.alice.%02d', $sequence->index + 1),
                'email' => sprintf('candidate.%02d@example.test', $sequence->index + 1),
                'extension' => sprintf('31%02d', $sequence->index + 1),
            ],
        )->create();
        $exact = SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Exact username match',
            'first_name' => 'Exact',
            'last_name' => 'Match',
            'username' => 'alice',
            'email' => 'exact@example.test',
            'extension' => '3999',
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/search?q=alice&types[]=extension",
        );

        $response->assertOk()
            ->assertJsonPath('data.groups.0.results.0.id', $exact->id);
    }

    public function test_like_wildcards_are_searched_as_literal_characters(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $literal = SwitchDevice::factory()->for($account)->create(['name' => 'Reception %_ phone']);
        SwitchDevice::factory()->for($account)->create(['name' => 'Reception ordinary phone']);

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/search?q=%25_&types[]=device",
        );

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.groups.0.results.0.id', $literal->id);
    }

    public function test_type_filter_and_result_cap_are_enforced(): void
    {
        [$user, $account] = $this->accessibleAccount();
        SwitchDevice::factory()->for($account)->count(7)->create(['name' => 'Searchable phone']);
        SwitchExtension::factory()->for($account)->create(['display_name' => 'Searchable person']);

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/search?q=searchable&types[]=device",
        );

        $response->assertOk()
            ->assertJsonPath('data.total', 5)
            ->assertJsonCount(1, 'data.groups')
            ->assertJsonPath('data.groups.0.type', 'device')
            ->assertJsonCount(5, 'data.groups.0.results');
    }

    public function test_search_never_returns_another_accounts_projection(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherAccount = SwitchAccount::factory()->create();
        SwitchDevice::factory()->for($account)->create(['name' => 'Shared Reception']);
        $foreign = SwitchDevice::factory()->for($otherAccount)->create(['name' => 'Shared Secret']);

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/search?q=shared&types[]=device",
        );

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonMissing(['id' => $foreign->id]);
    }

    public function test_search_validates_query_and_types(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/search?q=a")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/search?q=alice&types[]=unknown")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['types.0']);
    }

    public function test_search_requires_authentication_and_access_to_the_account(): void
    {
        [, $account] = $this->accessibleAccount();

        $this->getJson("/api/v1/accounts/{$account->id}/search?q=alice")
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/accounts/{$account->id}/search?q=alice")
            ->assertNotFound();
    }

    public function test_search_omits_resource_types_the_user_cannot_view(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $media = SwitchMedia::factory()->for($account)->create(['name' => 'Shared support media']);
        SwitchRecording::factory()->for($account)->create(['name' => 'Shared support recording']);

        Gate::before(static function (User $authorizedUser, string $ability, array $arguments) use ($user): ?bool {
            if (
                $authorizedUser->is($user)
                && $ability === 'viewAny'
                && ($arguments[0] ?? null) === SwitchRecording::class
            ) {
                return false;
            }

            return null;
        });

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/search?q=shared&types[]=media&types[]=recording",
        );

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonCount(1, 'data.groups')
            ->assertJsonPath('data.groups.0.type', 'media')
            ->assertJsonPath('data.groups.0.results.0.id', $media->id)
            ->assertJsonMissing(['type' => 'recording']);
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
