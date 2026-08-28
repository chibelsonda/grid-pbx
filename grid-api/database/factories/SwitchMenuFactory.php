<?php

namespace Database\Factories;

use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchMenu> */
class SwitchMenuFactory extends Factory
{
    protected $model = SwitchMenu::class;

    public function definition(): array
    {
        return [
            'switch_account_id' => SwitchAccount::factory(), 'switch_resource_id' => fake()->uuid(),
            'name' => fake()->words(2, true), 'timeout' => 10000, 'interdigit_timeout' => 2000,
            'max_extension_length' => 4, 'retries' => 3, 'hunt' => true,
            'allow_record_from_offnet' => false, 'suppress_media' => false,
            'record_pin_configured' => false,
            'invalid_media_enabled' => true, 'transfer_media_enabled' => true, 'exit_media_enabled' => true,
            'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1,
            'switch_json' => [],
        ];
    }
}
