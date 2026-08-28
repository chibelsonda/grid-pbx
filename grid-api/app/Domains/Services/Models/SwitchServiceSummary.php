<?php

namespace App\Domains\Services\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchServiceSummaryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SwitchServiceSummary extends Model
{
    use HasFactory, HasPublicUuid, HasUlids;

    protected $primaryKey = 'service_summary_id';

    protected $fillable = ['switch_account_id', 'status_acceptable', 'status_reason', 'is_reseller', 'billing_cycle_period', 'billing_cycle_unit', 'billing_cycle_next_at', 'assigned_plan_count', 'invoice_count', 'due_today', 'recurring_amount', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json'];

    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(SwitchServicePlan::class, 'switch_account_id', 'switch_account_id');
    }

    public function quantities(): HasMany
    {
        return $this->hasMany(SwitchServiceQuantity::class, 'switch_account_id', 'switch_account_id');
    }

    protected function casts(): array
    {
        return ['status_acceptable' => 'boolean', 'is_reseller' => 'boolean', 'billing_cycle_period' => 'integer', 'billing_cycle_next_at' => 'datetime', 'assigned_plan_count' => 'integer', 'invoice_count' => 'integer', 'due_today' => 'decimal:4', 'recurring_amount' => 'decimal:4', 'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class, 'projection_version' => 'integer', 'switch_json' => 'array'];
    }

    protected static function newFactory(): SwitchServiceSummaryFactory
    {
        return SwitchServiceSummaryFactory::new();
    }
}
