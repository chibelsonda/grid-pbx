<?php

namespace App\Domains\Services\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwitchServiceLimit extends Model
{
    use HasPublicUuid;

    protected $primaryKey = 'service_limit_id';

    protected $fillable = ['switch_account_id', 'enabled', 'allow_prepay', 'allow_postpay', 'inbound_trunks', 'outbound_trunks', 'twoway_trunks', 'burst_trunks', 'calls', 'resource_consuming_calls', 'soft_limit_inbound', 'soft_limit_outbound', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json'];

    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'allow_prepay' => 'boolean', 'allow_postpay' => 'boolean', 'inbound_trunks' => 'integer', 'outbound_trunks' => 'integer', 'twoway_trunks' => 'integer', 'burst_trunks' => 'integer', 'calls' => 'integer', 'resource_consuming_calls' => 'integer', 'soft_limit_inbound' => 'boolean', 'soft_limit_outbound' => 'boolean', 'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class, 'projection_version' => 'integer', 'switch_json' => 'array'];
    }
}
