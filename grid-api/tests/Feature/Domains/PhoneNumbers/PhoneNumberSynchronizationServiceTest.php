<?php

namespace Tests\Feature\Domains\PhoneNumbers;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Contracts\SwitchPhoneNumberGateway;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\PhoneNumbers\Services\PhoneNumberSynchronizationService;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Generator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PhoneNumberSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_projects_full_details_links_callflows_and_soft_deletes_missing_numbers(): void
    {
        $account = SwitchAccount::factory()->create();
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'numbers' => ['+14155550100'],
        ]);
        $missing = SwitchPhoneNumber::factory()->for($account)->create(['number' => '+14155550999']);
        $run = $account->syncRuns()->create([
            'requested_by_user_id' => User::factory()->create()->getKey(),
            'resource_type' => 'phone_numbers',
            'status' => SyncRunStatus::Queued,
        ]);
        $this->app->instance(SwitchPhoneNumberGateway::class, new class implements SwitchPhoneNumberGateway
        {
            public function all(SwitchAccount $account): Generator
            {
                yield [
                    'id' => '+14155550100',
                    'state' => 'in_service',
                    'used_by' => 'callflow',
                    'carrier_name' => 'local',
                    'features' => ['local', 'inbound_cnam'],
                    'cnam' => ['display_name' => 'GridPBX', 'inbound_lookup' => true],
                    'e911' => ['status' => 'PROVISIONED'],
                    '_read_only' => [
                        'assigned_to' => 'switch-account-1',
                        'created' => 63627848989,
                        'modified' => 63627849999,
                    ],
                    'api_key' => 'must-not-be-stored',
                ];
                yield [
                    'id' => '+14155550101',
                    '_read_only' => ['state' => 'reserved', 'features' => ['local']],
                ];
            }
        });

        $this->app->make(PhoneNumberSynchronizationService::class)->handle($run);

        $assigned = SwitchPhoneNumber::query()->where('number', '+14155550100')->firstOrFail();
        $reserved = SwitchPhoneNumber::query()->where('number', '+14155550101')->firstOrFail();
        $this->assertSame($callflow->getKey(), $assigned->assigned_callflow_id);
        $this->assertSame('in_service', $assigned->state);
        $this->assertSame(['local', 'inbound_cnam'], $assigned->features);
        $this->assertSame('GridPBX', $assigned->cnam_display_name);
        $this->assertTrue($assigned->cnam_inbound_lookup);
        $this->assertSame('PROVISIONED', $assigned->e911_status);
        $this->assertSame('[REDACTED]', $assigned->switch_json['api_key']);
        $this->assertSame('reserved', $reserved->state);
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('switch_sync_runs', [
            'sync_run_id' => $run->getKey(),
            'status' => 'succeeded',
            'processed_count' => 2,
            'upserted_count' => 2,
            'deleted_count' => 1,
        ]);
        $this->assertDatabaseHas('switch_sync_checkpoints', [
            'switch_account_id' => $account->getKey(),
            'resource_type' => 'phone_numbers',
            'status' => 'healthy',
        ]);
    }
}
