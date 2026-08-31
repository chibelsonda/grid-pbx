<?php

namespace App\Domains\Payments\Services;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Contracts\PaymentReversalGateway;
use App\Domains\Payments\Dto\PaymentChargeOutcome;
use App\Domains\Payments\Dto\PaymentMutationResult;
use App\Domains\Payments\Dto\PaymentReversalCommand;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Exceptions\PaymentIdempotencyConflictException;
use App\Domains\Payments\Exceptions\PaymentMutationUnavailableException;
use App\Domains\Payments\Models\PaymentAttempt;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PaymentReversalService
{
    public function __construct(private readonly PaymentReversalGateway $gateway) {}

    public function reverse(
        SwitchAccount $account,
        User $user,
        PaymentAttempt $source,
        PaymentReversalCommand $command,
    ): PaymentChargeOutcome {
        $this->assertAvailable($account, $source, $command);

        $idempotencyHash = $this->secureHash($command->idempotencyKey);
        $requestFingerprint = $this->requestFingerprint($account, $source, $command);
        $lockName = "payment-source:{$account->getKey()}:{$source->getKey()}";

        try {
            /** @var PaymentChargeOutcome $outcome */
            $outcome = Cache::lock($lockName, 20)->block(5, function () use (
                $account,
                $user,
                $source,
                $command,
                $idempotencyHash,
                $requestFingerprint,
            ): PaymentChargeOutcome {
                $attempt = PaymentAttempt::query()
                    ->where('switch_account_id', $account->getKey())
                    ->where('provider', 'authorize_net')
                    ->where('idempotency_hash', $idempotencyHash)
                    ->first();

                if ($attempt !== null) {
                    if (! hash_equals($attempt->request_fingerprint, $requestFingerprint)) {
                        throw new PaymentIdempotencyConflictException;
                    }

                    return new PaymentChargeOutcome($attempt, true);
                }

                $this->assertLocalReversalBalance($source, $command);

                $attempt = DB::transaction(function () use (
                    $account,
                    $user,
                    $source,
                    $command,
                    $idempotencyHash,
                    $requestFingerprint,
                ): PaymentAttempt {
                    $amountMinor = $command->operation === PaymentOperation::Void
                        ? $this->sourceAmountMinor($source)
                        : $command->amountMinor;

                    $attempt = PaymentAttempt::query()->create([
                        'switch_account_id' => $account->getKey(),
                        'requested_by_user_id' => $user->getKey(),
                        'source_payment_attempt_id' => $source->getKey(),
                        'provider' => 'authorize_net',
                        'operation' => $command->operation,
                        'idempotency_hash' => $idempotencyHash,
                        'request_fingerprint' => $requestFingerprint,
                        'amount' => $this->decimalAmount((int) $amountMinor),
                        'currency' => $command->currency,
                        'status' => PaymentAttemptStatus::Pending,
                    ]);

                    $attempt->events()->create([
                        'event_type' => 'attempt_created',
                        'status' => PaymentAttemptStatus::Pending,
                        'safe_context' => [
                            'source_attempt_id' => $source->id,
                            'operation' => $command->operation->value,
                        ],
                    ]);

                    return $attempt;
                });

                return new PaymentChargeOutcome($attempt, false);
            });
        } catch (LockTimeoutException) {
            throw new PaymentMutationUnavailableException('Another payment request is still being processed.');
        }

        if ($outcome->replayed) {
            return $outcome;
        }

        try {
            $providerReference = (string) $source->provider_reference;
            $result = $command->operation === PaymentOperation::Void
                ? $this->gateway->void($providerReference, $outcome->attempt->id)
                : $this->gateway->refund(
                    $providerReference,
                    (int) $command->amountMinor,
                    $command->currency,
                    $outcome->attempt->id,
                );
        } catch (Throwable) {
            $result = new PaymentMutationResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_processing_interrupted',
            );
        }

        return new PaymentChargeOutcome($this->complete($outcome->attempt, $result), false);
    }

    private function assertAvailable(
        SwitchAccount $account,
        PaymentAttempt $source,
        PaymentReversalCommand $command,
    ): void {
        $flag = match ($command->operation) {
            PaymentOperation::Void => 'sandbox_void_enabled',
            PaymentOperation::Refund => 'sandbox_refund_enabled',
            default => throw new PaymentMutationUnavailableException,
        };

        $available = (bool) config('payments.enabled', false)
            && (bool) config('payments.mutations_enabled', false)
            && (bool) config("payments.authorize_net.{$flag}", false)
            && config('payments.provider') === 'authorize_net'
            && strtolower((string) config('payments.authorize_net.environment')) === 'sandbox'
            && filled(config('payments.authorize_net.api_login_id'))
            && filled(config('payments.authorize_net.transaction_key'));

        $validSource = $source->switch_account_id === $account->getKey()
            && $source->provider === 'authorize_net'
            && $source->operation === PaymentOperation::Charge
            && $source->status === PaymentAttemptStatus::Succeeded
            && filled($source->provider_reference);

        $validAmount = $command->operation === PaymentOperation::Void
            ? $command->amountMinor === null
            : $command->amountMinor !== null && $command->amountMinor > 0;

        if (! $available || ! $validSource || ! $validAmount || $command->currency !== 'USD') {
            throw new PaymentMutationUnavailableException;
        }
    }

    private function assertLocalReversalBalance(
        PaymentAttempt $source,
        PaymentReversalCommand $command,
    ): void {
        $reservedStatuses = [
            PaymentAttemptStatus::Pending,
            PaymentAttemptStatus::Succeeded,
            PaymentAttemptStatus::Indeterminate,
        ];
        $hasReservedVoid = $source->childAttempts()
            ->where('operation', PaymentOperation::Void)
            ->whereIn('status', $reservedStatuses)
            ->exists();

        if ($hasReservedVoid) {
            throw new PaymentMutationUnavailableException(
                'The source charge already has a void awaiting or holding a final result.',
            );
        }

        if ($command->operation === PaymentOperation::Void) {
            $hasReservedRefund = $source->childAttempts()
                ->where('operation', PaymentOperation::Refund)
                ->whereIn('status', $reservedStatuses)
                ->exists();

            if ($hasReservedRefund) {
                throw new PaymentMutationUnavailableException(
                    'The source charge already has a refund awaiting or holding a final result.',
                );
            }

            return;
        }

        $refundedMinor = (int) round(
            (float) $source->childAttempts()
                ->where('operation', PaymentOperation::Refund)
                ->whereIn('status', $reservedStatuses)
                ->sum('amount') * 100,
        );

        if ($refundedMinor + (int) $command->amountMinor > $this->sourceAmountMinor($source)) {
            throw new PaymentMutationUnavailableException('The refund exceeds the remaining charge balance.');
        }
    }

    private function complete(PaymentAttempt $attempt, PaymentMutationResult $result): PaymentAttempt
    {
        return DB::transaction(function () use ($attempt, $result): PaymentAttempt {
            $providerReferenceHash = $result->providerReference !== null
                ? $this->secureHash($result->providerReference)
                : null;

            $attempt->forceFill([
                'status' => $result->status,
                'provider_reference' => $result->providerReference,
                'provider_reference_hash' => $providerReferenceHash,
                'safe_error_code' => $result->safeErrorCode,
                'completed_at' => now(),
            ])->save();

            $attempt->events()->create([
                'event_type' => 'provider_result_recorded',
                'status' => $result->status,
                'provider_reference_hash' => $providerReferenceHash,
                'safe_context' => ['safe_error_code' => $result->safeErrorCode],
            ]);

            return $attempt->refresh();
        });
    }

    private function requestFingerprint(
        SwitchAccount $account,
        PaymentAttempt $source,
        PaymentReversalCommand $command,
    ): string {
        return $this->secureHash(implode('|', [
            (string) $account->getKey(),
            (string) $source->getKey(),
            $command->operation->value,
            (string) $command->amountMinor,
            $command->currency,
        ]));
    }

    private function sourceAmountMinor(PaymentAttempt $source): int
    {
        return (int) round((float) $source->amount * 100);
    }

    private function secureHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function decimalAmount(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2, '.', '');
    }
}
