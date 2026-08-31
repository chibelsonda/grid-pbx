<?php

namespace App\Domains\Payments\Services;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Contracts\PaymentProfileGateway;
use App\Domains\Payments\Dto\PaymentProfileCommand;
use App\Domains\Payments\Dto\PaymentProfileOutcome;
use App\Domains\Payments\Dto\PaymentProfileResult;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Exceptions\PaymentIdempotencyConflictException;
use App\Domains\Payments\Exceptions\PaymentMutationUnavailableException;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentCustomerProfile;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PaymentProfileService
{
    public function __construct(private readonly PaymentProfileGateway $gateway) {}

    public function createFromCharge(
        SwitchAccount $account,
        User $user,
        PaymentAttempt $source,
        PaymentProfileCommand $command,
    ): PaymentProfileOutcome {
        $this->assertAvailable($account, $source);

        $idempotencyHash = $this->secureHash($command->idempotencyKey);
        $requestFingerprint = $this->secureHash(implode('|', [
            (string) $account->getKey(),
            (string) $source->getKey(),
            PaymentOperation::AttachPaymentMethod->value,
        ]));
        $lockName = "payment-source:{$account->getKey()}:{$source->getKey()}";

        try {
            /** @var PaymentProfileOutcome $outcome */
            $outcome = Cache::lock($lockName, 20)->block(5, function () use (
                $account,
                $user,
                $source,
                $idempotencyHash,
                $requestFingerprint,
            ): PaymentProfileOutcome {
                $attempt = PaymentAttempt::query()
                    ->where('switch_account_id', $account->getKey())
                    ->where('provider', 'authorize_net')
                    ->where('idempotency_hash', $idempotencyHash)
                    ->first();

                if ($attempt !== null) {
                    if (! hash_equals($attempt->request_fingerprint, $requestFingerprint)) {
                        throw new PaymentIdempotencyConflictException;
                    }

                    return new PaymentProfileOutcome(
                        $attempt,
                        PaymentCustomerProfile::query()
                            ->where('created_by_payment_attempt_id', $attempt->getKey())
                            ->first(),
                        true,
                    );
                }

                if (PaymentCustomerProfile::query()
                    ->where('source_payment_attempt_id', $source->getKey())
                    ->where('provider', 'authorize_net')
                    ->exists()) {
                    throw new PaymentMutationUnavailableException(
                        'A payment profile already exists for the source charge.',
                    );
                }

                if ($source->childAttempts()
                    ->where('operation', PaymentOperation::AttachPaymentMethod)
                    ->whereIn('status', [
                        PaymentAttemptStatus::Pending,
                        PaymentAttemptStatus::Succeeded,
                        PaymentAttemptStatus::Indeterminate,
                    ])
                    ->exists()) {
                    throw new PaymentMutationUnavailableException(
                        'Payment profile creation is already awaiting or holding a final result.',
                    );
                }

                $attempt = DB::transaction(function () use (
                    $account,
                    $user,
                    $source,
                    $idempotencyHash,
                    $requestFingerprint,
                ): PaymentAttempt {
                    $attempt = PaymentAttempt::query()->create([
                        'switch_account_id' => $account->getKey(),
                        'requested_by_user_id' => $user->getKey(),
                        'source_payment_attempt_id' => $source->getKey(),
                        'provider' => 'authorize_net',
                        'operation' => PaymentOperation::AttachPaymentMethod,
                        'idempotency_hash' => $idempotencyHash,
                        'request_fingerprint' => $requestFingerprint,
                        'status' => PaymentAttemptStatus::Pending,
                    ]);

                    $attempt->events()->create([
                        'event_type' => 'attempt_created',
                        'status' => PaymentAttemptStatus::Pending,
                        'safe_context' => [
                            'source_attempt_id' => $source->id,
                            'operation' => PaymentOperation::AttachPaymentMethod->value,
                        ],
                    ]);

                    return $attempt;
                });

                return new PaymentProfileOutcome($attempt, null, false);
            });
        } catch (LockTimeoutException) {
            throw new PaymentMutationUnavailableException('Another payment request is still being processed.');
        }

        if ($outcome->replayed) {
            return $outcome;
        }

        try {
            $result = $this->gateway->createFromTransaction(
                (string) $source->provider_reference,
                substr(hash('sha256', (string) $account->id), 0, 20),
                'GridPBX account '.substr((string) $account->id, 0, 36),
                filled($user->email) ? (string) $user->email : null,
            );
        } catch (Throwable) {
            $result = new PaymentProfileResult(
                PaymentAttemptStatus::Indeterminate,
                safeErrorCode: 'provider_processing_interrupted',
            );
        }

        return $this->complete($account, $source, $outcome->attempt, $result);
    }

    private function assertAvailable(SwitchAccount $account, PaymentAttempt $source): void
    {
        $available = (bool) config('payments.enabled', false)
            && (bool) config('payments.mutations_enabled', false)
            && (bool) config('payments.authorize_net.sandbox_profile_enabled', false)
            && config('payments.provider') === 'authorize_net'
            && strtolower((string) config('payments.authorize_net.environment')) === 'sandbox'
            && filled(config('payments.authorize_net.api_login_id'))
            && filled(config('payments.authorize_net.transaction_key'));

        $validSource = $source->switch_account_id === $account->getKey()
            && $source->provider === 'authorize_net'
            && $source->operation === PaymentOperation::Charge
            && $source->status === PaymentAttemptStatus::Succeeded
            && filled($source->provider_reference);

        if (! $available || ! $validSource) {
            throw new PaymentMutationUnavailableException;
        }
    }

    private function complete(
        SwitchAccount $account,
        PaymentAttempt $source,
        PaymentAttempt $attempt,
        PaymentProfileResult $result,
    ): PaymentProfileOutcome {
        return DB::transaction(function () use ($account, $source, $attempt, $result): PaymentProfileOutcome {
            $providerReferenceHash = $result->providerCustomerProfileId !== null
                ? $this->secureHash($result->providerCustomerProfileId)
                : null;

            $attempt->forceFill([
                'status' => $result->status,
                'provider_reference' => $result->providerCustomerProfileId,
                'provider_reference_hash' => $providerReferenceHash,
                'safe_error_code' => $result->safeErrorCode,
                'completed_at' => now(),
            ])->save();

            $profile = null;

            if (
                $result->status === PaymentAttemptStatus::Succeeded
                && $result->providerCustomerProfileId !== null
                && $result->providerPaymentProfileId !== null
            ) {
                $profile = PaymentCustomerProfile::query()->create([
                    'switch_account_id' => $account->getKey(),
                    'source_payment_attempt_id' => $source->getKey(),
                    'created_by_payment_attempt_id' => $attempt->getKey(),
                    'provider' => 'authorize_net',
                    'provider_customer_profile_id' => $result->providerCustomerProfileId,
                    'provider_customer_profile_hash' => $this->secureHash(
                        $result->providerCustomerProfileId,
                    ),
                    'provider_payment_profile_id' => $result->providerPaymentProfileId,
                    'provider_payment_profile_hash' => $this->secureHash(
                        $result->providerPaymentProfileId,
                    ),
                    'status' => 'active',
                ]);
            }

            $attempt->events()->create([
                'event_type' => 'provider_result_recorded',
                'status' => $result->status,
                'provider_reference_hash' => $providerReferenceHash,
                'safe_context' => [
                    'safe_error_code' => $result->safeErrorCode,
                    'profile_created' => $profile !== null,
                ],
            ]);

            return new PaymentProfileOutcome($attempt->refresh(), $profile?->refresh(), false);
        });
    }

    private function secureHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
