<?php

namespace App\Domains\Payments\Services;

final class AuthorizeNetWebhookSignatureVerifier
{
    public function configured(): bool
    {
        $key = $this->signatureKey();

        return strlen($key) === 128 && ctype_xdigit($key);
    }

    public function verify(string $rawBody, ?string $signatureHeader): bool
    {
        if (! $this->configured() || ! is_string($signatureHeader)) {
            return false;
        }

        $provided = preg_replace('/^sha512=/i', '', trim($signatureHeader));

        if (! is_string($provided) || strlen($provided) !== 128 || ! ctype_xdigit($provided)) {
            return false;
        }

        $binaryKey = hex2bin($this->signatureKey());

        if ($binaryKey === false) {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, $binaryKey);

        return hash_equals(strtolower($expected), strtolower($provided));
    }

    private function signatureKey(): string
    {
        return trim((string) config('payments.authorize_net.signature_key'));
    }
}
