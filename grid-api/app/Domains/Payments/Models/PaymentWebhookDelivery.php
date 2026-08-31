<?php

namespace App\Domains\Payments\Models;

use App\Domains\Payments\Enums\PaymentWebhookDeliveryStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookDelivery extends Model
{
    use HasPublicUuid;

    protected $primaryKey = 'payment_webhook_delivery_id';

    protected $fillable = [
        'provider',
        'notification_hash',
        'event_type',
        'entity_name',
        'provider_reference',
        'provider_reference_hash',
        'merchant_reference',
        'payment_attempt_id',
        'status',
        'processing_attempts',
        'safe_error_code',
        'event_occurred_at',
        'received_at',
        'processed_at',
    ];

    protected $hidden = [
        'payment_webhook_delivery_id',
        'notification_hash',
        'provider_reference',
        'provider_reference_hash',
        'merchant_reference',
        'payment_attempt_id',
    ];

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(
            PaymentAttempt::class,
            'payment_attempt_id',
            'payment_attempt_id',
        );
    }

    protected function casts(): array
    {
        return [
            'provider_reference' => 'encrypted',
            'status' => PaymentWebhookDeliveryStatus::class,
            'event_occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
