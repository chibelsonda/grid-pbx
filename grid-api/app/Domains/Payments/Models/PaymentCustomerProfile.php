<?php

namespace App\Domains\Payments\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCustomerProfile extends Model
{
    use HasPublicUuid;

    protected $primaryKey = 'payment_customer_profile_id';

    protected $fillable = [
        'switch_account_id',
        'source_payment_attempt_id',
        'created_by_payment_attempt_id',
        'provider',
        'provider_customer_profile_id',
        'provider_customer_profile_hash',
        'provider_payment_profile_id',
        'provider_payment_profile_hash',
        'status',
        'masked_account',
        'account_type',
    ];

    protected $hidden = [
        'payment_customer_profile_id',
        'switch_account_id',
        'source_payment_attempt_id',
        'created_by_payment_attempt_id',
        'provider_customer_profile_id',
        'provider_customer_profile_hash',
        'provider_payment_profile_id',
        'provider_payment_profile_hash',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function sourceAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'source_payment_attempt_id', 'payment_attempt_id');
    }

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function createdByAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'created_by_payment_attempt_id', 'payment_attempt_id');
    }

    protected function casts(): array
    {
        return [
            'provider_customer_profile_id' => 'encrypted',
            'provider_payment_profile_id' => 'encrypted',
        ];
    }
}
