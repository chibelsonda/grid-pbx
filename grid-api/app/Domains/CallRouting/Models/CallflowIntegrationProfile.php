<?php

namespace App\Domains\CallRouting\Models;

use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\CallflowIntegrationProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CallflowIntegrationProfile extends Model
{
    /** @use HasFactory<CallflowIntegrationProfileFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $primaryKey = 'callflow_integration_profile_id';

    protected $fillable = [
        'switch_account_id',
        'created_by_user_id',
        'updated_by_user_id',
        'integration_type',
        'name',
        'is_active',
        'settings',
    ];

    protected $hidden = [
        'callflow_integration_profile_id',
        'switch_account_id',
        'created_by_user_id',
        'updated_by_user_id',
        'settings',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id', 'user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'integration_type' => CallflowIntegrationType::class,
            'is_active' => 'boolean',
            'settings' => 'encrypted:array',
        ];
    }

    protected static function newFactory(): CallflowIntegrationProfileFactory
    {
        return CallflowIntegrationProfileFactory::new();
    }
}
