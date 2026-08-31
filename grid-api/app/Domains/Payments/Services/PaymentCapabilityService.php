<?php

namespace App\Domains\Payments\Services;

final class PaymentCapabilityService
{
    public function __construct(
        private readonly AuthorizeNetWebhookSignatureVerifier $webhookSignatures,
    ) {}

    /** @return array<string, mixed> */
    public function get(): array
    {
        $provider = (string) config('payments.provider', 'unavailable');
        $environment = strtolower(trim((string) config('payments.authorize_net.environment')));
        $loginConfigured = filled(config('payments.authorize_net.api_login_id'));
        $transactionKeyConfigured = filled(config('payments.authorize_net.transaction_key'));
        $providerSupported = $provider === 'authorize_net';
        $sandbox = $environment === 'sandbox';
        $enabled = (bool) config('payments.enabled', false);
        $configured = $providerSupported && $loginConfigured && $transactionKeyConfigured;
        $publicClientConfigured = filled(config('payments.authorize_net.public_client_key'));
        $chargeAvailable = $enabled
            && (bool) config('payments.mutations_enabled', false)
            && (bool) config('payments.authorize_net.sandbox_charge_enabled', false)
            && $configured
            && $publicClientConfigured
            && $sandbox;
        $serverMutationAvailable = $enabled
            && (bool) config('payments.mutations_enabled', false)
            && $configured
            && $sandbox;
        $voidAvailable = $serverMutationAvailable
            && (bool) config('payments.authorize_net.sandbox_void_enabled', false);
        $refundAvailable = $serverMutationAvailable
            && (bool) config('payments.authorize_net.sandbox_refund_enabled', false);
        $profileAvailable = $serverMutationAvailable
            && (bool) config('payments.authorize_net.sandbox_profile_enabled', false);
        $webhookConfigured = $configured && $sandbox && $this->webhookSignatures->configured();
        $webhookEnabled = (bool) config('payments.authorize_net.webhook_enabled', false);

        return [
            'enabled' => $enabled,
            'provider' => $provider,
            'environment' => $sandbox ? 'sandbox' : 'unsupported',
            'configured' => $configured,
            'capture_strategy' => 'hosted_or_tokenized',
            'server_accepts_card_data' => false,
            'configuration' => [
                'public_client_key_configured' => $publicClientConfigured,
                'signature_key_configured' => $this->webhookSignatures->configured(),
            ],
            'client' => [
                'available' => $chargeAvailable,
                'accept_ui_url' => $chargeAvailable
                    ? (string) config('payments.authorize_net.accept_ui_url')
                    : null,
                'api_login_id' => $chargeAvailable
                    ? (string) config('payments.authorize_net.api_login_id')
                    : null,
                'public_client_key' => $chargeAvailable
                    ? (string) config('payments.authorize_net.public_client_key')
                    : null,
                'sandbox_max_charge_minor' => $chargeAvailable
                    ? (int) config('payments.authorize_net.sandbox_max_charge_minor', 100)
                    : null,
                'sandbox_max_refund_minor' => $refundAvailable
                    ? (int) config('payments.authorize_net.sandbox_max_refund_minor', 100)
                    : null,
            ],
            'diagnostics' => [
                'available' => $configured && $sandbox,
                'sandbox_only' => true,
            ],
            'webhooks' => [
                'enabled' => $webhookEnabled,
                'configured' => $webhookConfigured,
                'accepting' => $webhookEnabled && $webhookConfigured,
            ],
            'mutations' => [
                'attach_payment_method' => $profileAvailable,
                'charge' => $chargeAvailable,
                'void' => $voidAvailable,
                'refund' => $refundAvailable,
            ],
        ];
    }
}
