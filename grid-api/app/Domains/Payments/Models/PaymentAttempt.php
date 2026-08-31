<?php

namespace App\Domains\Payments\Models;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAttempt extends Model
{
    use HasPublicUuid;

    protected $primaryKey = 'payment_attempt_id';

    protected $fillable = [
        'switch_account_id',
        'requested_by_user_id',
        'source_payment_attempt_id',
        'provider',
        'operation',
        'idempotency_hash',
        'request_fingerprint',
        'amount',
        'currency',
        'status',
        'provider_reference',
        'provider_reference_hash',
        'safe_error_code',
        'provider_status',
        'reconciled_at',
        'completed_at',
    ];

    protected $hidden = [
        'payment_attempt_id',
        'switch_account_id',
        'requested_by_user_id',
        'source_payment_attempt_id',
        'idempotency_hash',
        'request_fingerprint',
        'provider_reference',
        'provider_reference_hash',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id', 'user_id');
    }

    /** @return HasMany<PaymentAttemptEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentAttemptEvent::class, 'payment_attempt_id', 'payment_attempt_id');
    }

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function sourceAttempt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_payment_attempt_id', 'payment_attempt_id');
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function childAttempts(): HasMany
    {
        return $this->hasMany(self::class, 'source_payment_attempt_id', 'payment_attempt_id');
    }

    /** @return HasMany<PaymentCustomerProfile, $this> */
    public function createdCustomerProfiles(): HasMany
    {
        return $this->hasMany(
            PaymentCustomerProfile::class,
            'created_by_payment_attempt_id',
            'payment_attempt_id',
        );
    }

    protected function casts(): array
    {
        return [
            'operation' => PaymentOperation::class,
            'amount' => 'decimal:8',
            'status' => PaymentAttemptStatus::class,
            'provider_reference' => 'encrypted',
            'reconciled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
