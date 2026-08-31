<?php

namespace App\Domains\Payments\Services;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Contracts\PaymentChargeGateway;
use App\Domains\Payments\Dto\PaymentChargeCommand;
use App\Domains\Payments\Dto\PaymentChargeOutcome;
use App\Domains\Payments\Dto\PaymentMutationResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Exceptions\PaymentIdempotencyConflictException;
use App\Domains\Payments\Exceptions\PaymentMutationUnavailableException;
use App\Domains\Payments\Models\PaymentAttempt;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PaymentChargeService
{
    public function __construct(private readonly PaymentChargeGateway $gateway) {}

    public function charge(
        SwitchAccount $account,
        User $user,
        PaymentChargeCommand $command,
    ): PaymentChargeOutcome {
        $this->assertAvailable($command);

        $idempotencyHash = $this->secureHash($command->idempotencyKey);
        $requestFingerprint = $this->requestFingerprint($account, $command);
        $lockName = "payment-attempt:{$account->getKey()}:{$idempotencyHash}";

        try {
            /** @var PaymentChargeOutcome $outcome */
            $outcome = Cache::lock($lockName, 20)->block(5, function () use (
                $account,
                $user,
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

                $attempt = DB::transaction(function () use (
                    $account,
                    $user,
                    $command,
                    $idempotencyHash,
                    $requestFingerprint,
                ): PaymentAttempt {
                    $attempt = PaymentAttempt::query()->create([
                        'switch_account_id' => $account->getKey(),
                        'requested_by_user_id' => $user->getKey(),
                        'provider' => 'authorize_net',
                        'operation' => PaymentOperation::Charge,
                        'idempotency_hash' => $idempotencyHash,
                        'request_fingerprint' => $requestFingerprint,
                        'amount' => $this->decimalAmount($command->amountMinor),
                        'currency' => $command->currency,
                        'status' => PaymentAttemptStatus::Pending,
                    ]);

                    $attempt->events()->create([
                        'event_type' => 'attempt_created',
                        'status' => PaymentAttemptStatus::Pending,
                        'safe_context' => ['source' => 'sandbox_hosted_token'],
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
            $result = $this->gateway->charge($command, $outcome->attempt->id);
        } catch (Throwable) {
            $this->complete(
                $outcome->attempt,
                new PaymentMutationResult(
                    PaymentAttemptStatus::Indeterminate,
                    safeErrorCode: 'provider_processing_interrupted',
                ),
            );

            throw new PaymentMutationUnavailableException(
                'The provider result could not be confirmed. Review the attempt before retrying.',
            );
        }

        return new PaymentChargeOutcome(
            $this->complete($outcome->attempt, $result),
            false,
        );
    }

    private function assertAvailable(PaymentChargeCommand $command): void
    {
        $available = (bool) config('payments.enabled', false)
            && (bool) config('payments.mutations_enabled', false)
            && (bool) config('payments.authorize_net.sandbox_charge_enabled', false)
            && config('payments.provider') === 'authorize_net'
            && strtolower((string) config('payments.authorize_net.environment')) === 'sandbox'
            && filled(config('payments.authorize_net.api_login_id'))
            && filled(config('payments.authorize_net.transaction_key'))
            && filled(config('payments.authorize_net.public_client_key'));

        $withinLimit = $command->amountMinor > 0
            && $command->amountMinor <= (int) config('payments.authorize_net.sandbox_max_charge_minor', 100);

        if (! $available || ! $withinLimit || $command->currency !== 'USD') {
            throw new PaymentMutationUnavailableException;
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
        PaymentChargeCommand $command,
    ): string {
        return $this->secureHash(implode('|', [
            (string) $account->getKey(),
            (string) $command->amountMinor,
            $command->currency,
            $command->dataDescriptor,
            hash('sha256', $command->dataValue),
        ]));
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
