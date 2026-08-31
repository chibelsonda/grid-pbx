<?php

namespace App\Domains\Payments\Dto;

final readonly class PaymentProviderDiagnostic
{
    public function __construct(
        public string $provider,
        public string $environment,
        public bool $configured,
        public bool $reachable,
        public bool $authenticated,
        public ?bool $publicClientKeyMatches,
        public string $status,
    ) {}

    /** @return array<string, bool|string|null> */
    public function toSafeArray(): array
    {
        return [
            'provider' => $this->provider,
            'environment' => $this->environment,
            'configured' => $this->configured,
            'reachable' => $this->reachable,
            'authenticated' => $this->authenticated,
            'public_client_key_matches' => $this->publicClientKeyMatches,
            'status' => $this->status,
        ];
    }
}
