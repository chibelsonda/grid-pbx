<?php

namespace Tests\Feature\Domains\Faxes;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Faxes\Contracts\SwitchFaxBoxGateway;
use App\Domains\Faxes\Contracts\SwitchFaxGateway;
use App\Domains\Faxes\Models\SwitchFax;
use App\Domains\Faxes\Services\FaxSynchronizationService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FaxSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_projects_boxes_and_bounded_message_metadata_while_redacting_document_locations(): void
    {
        CarbonImmutable::setTestNow('2026-08-28 12:00:00'); $account = SwitchAccount::factory()->create(); $owner = SwitchExtension::factory()->for($account)->create(['switch_resource_id' => 'switch-user-1']);
        $missing = SwitchFax::factory()->for($account)->create(['switch_resource_id' => 'missing', 'switch_created_at' => now()->subDay()]); $run = $account->syncRuns()->create(['requested_by_user_id' => User::factory()->create()->getKey(), 'resource_type' => 'faxes', 'status' => SyncRunStatus::Queued]);
        $this->mock(SwitchFaxBoxGateway::class)->shouldReceive('all')->once()->andReturn((function (): \Generator { yield ['id' => 'switch-box-1', 'name' => 'Main fax', 'owner_id' => 'switch-user-1', 'cloud_connector_claim_url' => 'https://secret.test/claim']; })());
        $this->mock(SwitchFaxGateway::class)->shouldReceive('all')->twice()->andReturnUsing(function (SwitchAccount $received, string $folder): \Generator { if ($folder === 'inbox') yield ['switch_resource_id' => '202608-fax-1', 'folder' => 'inbox', 'status' => 'completed', 'fax_box_switch_resource_id' => 'switch-box-1', 'owner_switch_resource_id' => 'switch-user-1', 'from_number' => '+12025550101', 'to_number' => '+12025550100', 'successful' => true, 'pages' => 2, 'switch_created_at_unix' => 1787918400, 'has_document' => true, 'document_content_type' => 'application/pdf', 'data' => ['id' => '202608-fax-1', 'document' => ['url' => 'https://signed.test/file', 'content' => 'secret body', 'content_type' => 'application/pdf']]]; });
        $this->app->make(FaxSynchronizationService::class)->handle($run);
        $box = $account->faxBoxes()->firstOrFail(); $fax = $account->faxes()->where('switch_resource_id', '202608-fax-1')->firstOrFail();
        $this->assertSame($owner->getKey(), $box->owner_extension_id); $this->assertSame('[REDACTED]', $box->switch_json['cloud_connector_claim_url']); $this->assertSame($box->getKey(), $fax->switch_fax_box_id); $this->assertSame('[REDACTED]', $fax->switch_json['document']['url']); $this->assertSame('[REDACTED]', $fax->switch_json['document']['content']); $this->assertSoftDeleted($missing); $this->assertDatabaseHas('switch_sync_checkpoints', ['switch_account_id' => $account->getKey(), 'resource_type' => 'faxes', 'status' => 'healthy']);
    }
}
