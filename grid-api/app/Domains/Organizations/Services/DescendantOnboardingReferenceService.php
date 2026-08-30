<?php

namespace App\Domains\Organizations\Services;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

class DescendantOnboardingReferenceService
{
    private const LIFETIME_MINUTES = 10;

    /** @return array{reference: string, expires_at: string} */
    public function issue(User $actor, SwitchAccount $scope, string $switchAccountId): array
    {
        $expiresAt = now()->addMinutes(self::LIFETIME_MINUTES);
        $payload = json_encode([
            'actor_id' => $actor->getKey(),
            'scope_account_id' => $scope->getKey(),
            'organization_id' => $scope->organization_id,
            'switch_account_id' => $switchAccountId,
            'expires_at' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        return [
            'reference' => Crypt::encryptString($payload),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function resolve(User $actor, SwitchAccount $scope, string $reference): string
    {
        try {
            $payload = json_decode(Crypt::decryptString($reference), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $this->invalidReference();
        }

        if (! is_array($payload)
            || ($payload['actor_id'] ?? null) !== $actor->getKey()
            || ($payload['scope_account_id'] ?? null) !== $scope->getKey()
            || ($payload['organization_id'] ?? null) !== $scope->organization_id
            || ! is_int($payload['expires_at'] ?? null)
            || $payload['expires_at'] < now()->getTimestamp()
            || ! is_string($payload['switch_account_id'] ?? null)
            || $payload['switch_account_id'] === '') {
            $this->invalidReference();
        }

        return $payload['switch_account_id'];
    }

    private function invalidReference(): never
    {
        throw ValidationException::withMessages([
            'reference' => 'The descendant reference is invalid or expired. Refresh the candidate list and try again.',
        ]);
    }
}
