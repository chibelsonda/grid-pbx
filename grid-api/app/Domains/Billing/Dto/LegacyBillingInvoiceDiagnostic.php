<?php

namespace App\Domains\Billing\Dto;

final readonly class LegacyBillingInvoiceDiagnostic
{
    public function __construct(
        public bool $providerSelected,
        public bool $adapterEnabled,
        public bool $authorityConfirmed,
        public bool $readOnlyConfirmed,
        public bool $connectionConfigured,
        public bool $connectionAttempted,
        public bool $connectionReady,
        public bool $readOnlyGrantVerified,
        public bool $schemaCompatible,
        public string $status,
        public string $guidance,
    ) {}

    public function ready(): bool
    {
        return $this->status === 'ready';
    }

    /**
     * Return only operational state. Connection details and database errors are intentionally excluded.
     *
     * @return array<string, bool|string>
     */
    public function toSafeArray(): array
    {
        return [
            'provider_selected' => $this->providerSelected,
            'adapter_enabled' => $this->adapterEnabled,
            'authority_confirmed' => $this->authorityConfirmed,
            'read_only_confirmed' => $this->readOnlyConfirmed,
            'connection_configured' => $this->connectionConfigured,
            'connection_attempted' => $this->connectionAttempted,
            'connection_ready' => $this->connectionReady,
            'read_only_grant_verified' => $this->readOnlyGrantVerified,
            'schema_compatible' => $this->schemaCompatible,
            'status' => $this->status,
            'guidance' => $this->guidance,
        ];
    }
}
