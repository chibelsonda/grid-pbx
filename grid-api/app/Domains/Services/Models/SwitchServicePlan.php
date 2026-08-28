<?php

namespace App\Domains\Services\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchServicePlan extends Model
{
    use HasPublicUuid, HasUlids, SoftDeletes;

    protected $primaryKey = 'service_plan_id';

    protected $fillable = ['switch_account_id', 'switch_resource_id', 'name', 'description', 'category', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json'];

    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class, 'projection_version' => 'integer', 'switch_json' => 'array'];
    }
}
