<?php

namespace Tests\Feature\Domains\Menus;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Menus\Contracts\SwitchMenuGateway;
use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Menus\Services\MenuSynchronizationService;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MenuSynchronizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_projects_menu_media_and_soft_deletes_missing_menus(): void
    {
        $account = SwitchAccount::factory()->create();
        $media = SwitchMedia::factory()->for($account)->create(['switch_resource_id' => 'switch-media-1']);
        $missing = SwitchMenu::factory()->for($account)->create(['switch_resource_id' => 'missing']);
        $run = $account->syncRuns()->create(['requested_by_user_id' => User::factory()->create()->getKey(), 'resource_type' => 'menus', 'status' => SyncRunStatus::Queued]);
        $this->mock(SwitchMenuGateway::class)->shouldReceive('all')->once()->andReturn((function (): \Generator {
            yield [
                'id' => 'switch-menu-1', 'name' => 'Main menu', 'timeout' => 8000,
                'interdigit_timeout' => 1500, 'max_extension_length' => 5, 'retries' => 2,
                'hunt' => false, 'record_pin' => '9876',
                'media' => ['greeting' => 'switch-media-1', 'invalid_media' => false, 'transfer_media' => true],
            ];
        })());

        $this->app->make(MenuSynchronizationService::class)->handle($run);

        $menu = SwitchMenu::query()->where('switch_resource_id', 'switch-menu-1')->firstOrFail();
        $this->assertSame($media->getKey(), $menu->greeting_media_id);
        $this->assertFalse($menu->invalid_media_enabled);
        $this->assertFalse($menu->hunt);
        $this->assertTrue($menu->record_pin_configured);
        $this->assertSame('Main menu', $menu->switch_json['name']);
        $this->assertSame('[REDACTED]', $menu->switch_json['record_pin']);
        $this->assertSoftDeleted($missing);
        $this->assertDatabaseHas('switch_sync_checkpoints', ['switch_account_id' => $account->getKey(), 'resource_type' => 'menus', 'status' => 'healthy']);
    }
}
