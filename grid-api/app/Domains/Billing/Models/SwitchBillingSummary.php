<?php

namespace App\Domains\Billing\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwitchBillingSummary extends Model
{
    use HasPublicUuid;

    protected $primaryKey = 'billing_summary_id';

    protected $fillable = [
        'switch_account_id',
        'ledger_total',
        'ledger_source_count',
        'transaction_count',
        'ledgers_available',
        'ledger_total_available',
        'transactions_available',
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
            'ledger_total' => 'decimal:8',
            'ledger_source_count' => 'integer',
            'transaction_count' => 'integer',
            'ledgers_available' => 'boolean',
            'ledger_total_available' => 'boolean',
            'transactions_available' => 'boolean',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'switch_json' => 'array',
        ];
    }
}
