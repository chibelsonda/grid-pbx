<?php

namespace App\Domains\Payments\Models;

use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentAttemptEvent extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    protected $primaryKey = 'payment_attempt_event_id';

    protected $fillable = [
        'payment_attempt_id',
        'event_type',
        'status',
        'provider_reference_hash',
        'safe_context',
    ];

    protected $hidden = [
        'payment_attempt_event_id',
        'payment_attempt_id',
        'provider_reference_hash',
    ];

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id', 'payment_attempt_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment attempt events are immutable.'));
        static::deleting(fn () => throw new LogicException('Payment attempt events are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'safe_context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
