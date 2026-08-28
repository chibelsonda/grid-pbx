<?php

namespace Database\Factories;

use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchFaxBox> */
class SwitchFaxBoxFactory extends Factory
{
    protected $model = SwitchFaxBox::class;
    public function definition(): array { return ['switch_account_id' => SwitchAccount::factory(), 'switch_resource_id' => fake()->uuid(), 'name' => fake()->words(2, true), 'retries' => 1, 't38_enabled' => false, 'smtp_permission_list' => [], 'inbound_notification_emails' => [], 'outbound_notification_emails' => [], 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1, 'switch_json' => []]; }
}
