<?php

namespace App\Domains\SwitchSynchronization\Services;

class RedactSensitiveSwitchData
{
    private const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'api_key',
        'auth_token',
        'billing_id',
        'bookkeeper',
        'bookkeepers',
        'cloud_connector_claim_url',
        'md5_auth',
        'password',
        'password_confirmation',
        'payment_tokens',
        'payment_token',
        'pin',
        'pins',
        'pvt_md5_auth',
        'pvt_sha1_auth',
        'secret',
        'secret_key',
        'sha1_auth',
        'token',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $data[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->handle($value);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = mb_strtolower(str_replace(['-', ' '], '_', $key));

        if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (['_password', '_pin', '_pins', '_secret', '_api_key', '_auth_token'] as $suffix) {
            if (str_ends_with($normalizedKey, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
