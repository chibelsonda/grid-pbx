<?php

namespace App\Domains\Conferences\Services;

use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

class ConferenceParticipantTokenService
{
    public function issue(
        SwitchAccount $account,
        SwitchConference $conference,
        string $switchParticipantId,
    ): string {
        return Crypt::encryptString(json_encode([
            'account' => $account->getKey(),
            'conference' => $conference->getKey(),
            'participant' => $switchParticipantId,
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    public function resolve(
        SwitchAccount $account,
        SwitchConference $conference,
        string $token,
    ): string {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw $this->invalidToken();
        }

        if (! is_array($payload)
            || ($payload['account'] ?? null) !== $account->getKey()
            || ($payload['conference'] ?? null) !== $conference->getKey()
            || ! is_string($payload['participant'] ?? null)
            || ! ctype_digit($payload['participant'])
            || ! is_int($payload['issued_at'] ?? null)
            || $payload['issued_at'] > now()->timestamp
            || now()->timestamp - $payload['issued_at'] > $this->ttl()
        ) {
            throw $this->invalidToken();
        }

        return $payload['participant'];
    }

    private function ttl(): int
    {
        return max(30, min(900, (int) config('switch.conference_participant_token_ttl', 300)));
    }

    private function invalidToken(): ValidationException
    {
        return ValidationException::withMessages([
            'participant_id' => ['Refresh the participant list and try again.'],
        ]);
    }
}
