<?php

namespace Database\Factories;

use App\Domains\Faxes\Models\SwitchFax;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SwitchFax> */
class SwitchFaxFactory extends Factory
{
    protected $model = SwitchFax::class;
    public function definition(): array { return ['switch_account_id' => SwitchAccount::factory(), 'switch_resource_id' => fake()->uuid(), 'folder' => 'inbox', 'status' => 'completed', 'from_number' => fake()->e164PhoneNumber(), 'to_number' => fake()->e164PhoneNumber(), 'attempts' => 0, 'retries' => 1, 'pages' => 1, 'fax_speed' => 14400, 'elapsed_seconds' => 10, 'switch_created_at' => now(), 'has_document' => true, 'document_content_type' => 'application/pdf', 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => 1, 'switch_json' => []]; }
}
