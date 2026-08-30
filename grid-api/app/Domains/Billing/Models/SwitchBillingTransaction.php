<?php

namespace App\Domains\Billing\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchBillingTransaction extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $primaryKey = 'billing_transaction_id';

    protected $fillable = [
        'switch_account_id',
        'switch_resource_id',
        'amount',
        'type',
        'reason',
        'description',
        'code',
        'switch_version',
        'switch_created_at',
        'last_synced_at',
        'sync_status',
        'projection_version',
        'switch_json',
    ];

    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'code' => 'integer',
            'switch_version' => 'integer',
            'switch_created_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'switch_json' => 'array',
        ];
    }
}
