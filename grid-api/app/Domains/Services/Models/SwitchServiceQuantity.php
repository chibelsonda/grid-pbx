<?php

namespace App\Domains\Services\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchServiceQuantity extends Model
{
    use HasPublicUuid, HasUlids, SoftDeletes;

    protected $primaryKey = 'service_quantity_id';

    protected $fillable = ['switch_account_id', 'scope', 'category', 'item', 'quantity', 'last_synced_at'];

    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'last_synced_at' => 'datetime'];
    }
}
