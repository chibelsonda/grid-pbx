<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Dto\PaymentWebhookReceipt;
use App\Domains\Payments\Enums\PaymentWebhookDeliveryStatus;
use App\Domains\Payments\Jobs\ReconcilePaymentWebhookJob;
use App\Domains\Payments\Models\PaymentWebhookDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PaymentWebhookIntakeService
{
    public function __construct(private readonly PaymentWebhookEventPolicy $events) {}

    /** @throws ValidationException */
    public function receive(string $rawBody): PaymentWebhookReceipt
    {
        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['webhook' => 'The webhook payload is invalid.']);
        }

        $validated = Validator::make($payload, [
            'notificationId' => ['required', 'string', 'max:64'],
            'eventType' => ['required', 'string', 'max:128'],
            'eventDate' => ['nullable', 'date'],
            'payload' => ['required', 'array'],
            'payload.entityName' => ['required', 'string', 'max:64'],
            'payload.id' => ['required', 'string', 'max:128'],
            'payload.merchantReferenceId' => ['nullable', 'string', 'max:64'],
        ])->validate();

        $notificationHash = $this->secureHash((string) $validated['notificationId']);
        $operation = $this->events->operation((string) $validated['eventType']);
        $isTransaction = $validated['payload']['entityName'] === 'transaction' && $operation !== null;
        $providerReference = $isTransaction ? trim((string) $validated['payload']['id']) : null;

        if ($isTransaction && $providerReference === '') {
            throw ValidationException::withMessages(['webhook' => 'The webhook payload is invalid.']);
        }

        try {
            /** @var array{PaymentWebhookDelivery, bool} $result */
            $result = DB::transaction(function () use (
                $validated,
                $notificationHash,
                $isTransaction,
                $providerReference,
            ): array {
                $existing = PaymentWebhookDelivery::query()
                    ->where('provider', 'authorize_net')
                    ->where('notification_hash', $notificationHash)
                    ->first();

                if ($existing !== null) {
                    return [$existing, true];
                }

                $delivery = PaymentWebhookDelivery::query()->create([
                    'provider' => 'authorize_net',
                    'notification_hash' => $notificationHash,
                    'event_type' => $validated['eventType'],
                    'entity_name' => $validated['payload']['entityName'],
                    'provider_reference' => $providerReference,
                    'provider_reference_hash' => $providerReference === null
                        ? null
                        : $this->secureHash($providerReference),
                    'merchant_reference' => $isTransaction
                        ? data_get($validated, 'payload.merchantReferenceId')
                        : null,
                    'status' => $isTransaction
                        ? PaymentWebhookDeliveryStatus::Received
                        : PaymentWebhookDeliveryStatus::Ignored,
                    'safe_error_code' => $isTransaction ? null : 'unsupported_event',
                    'event_occurred_at' => filled($validated['eventDate'] ?? null)
                        ? Carbon::parse((string) $validated['eventDate'])
                        : null,
                    'received_at' => now(),
                    'processed_at' => $isTransaction ? null : now(),
                ]);

                return [$delivery, false];
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $result = [
                PaymentWebhookDelivery::query()
                    ->where('provider', 'authorize_net')
                    ->where('notification_hash', $notificationHash)
                    ->firstOrFail(),
                true,
            ];
        }

        [$delivery, $replayed] = $result;

        if (! $replayed && $delivery->status === PaymentWebhookDeliveryStatus::Received) {
            ReconcilePaymentWebhookJob::dispatch($delivery->id)->afterCommit();
        }

        return new PaymentWebhookReceipt($delivery, $replayed);
    }

    private function secureHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
