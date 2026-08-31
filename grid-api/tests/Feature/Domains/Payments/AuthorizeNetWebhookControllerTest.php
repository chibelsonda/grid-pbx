<?php

namespace Tests\Feature\Domains\Payments;

use App\Domains\Payments\Jobs\ReconcilePaymentWebhookJob;
use App\Domains\Payments\Models\PaymentWebhookDelivery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthorizeNetWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_fails_closed_while_webhook_processing_is_disabled(): void
    {
        $this->configureWebhook(false);

        $this->call('POST', $this->endpoint(), content: $this->payload())
            ->assertServiceUnavailable()
            ->assertExactJson(['message' => 'Payment webhook processing is unavailable.']);

        $this->assertDatabaseCount('payment_webhook_deliveries', 0);
    }

    public function test_it_rejects_a_missing_or_invalid_signature_without_persisting_the_body(): void
    {
        $this->configureWebhook();
        $rawBody = $this->payload();

        $this->call('POST', $this->endpoint(), server: [
            'HTTP_X_ANET_SIGNATURE' => 'sha512='.str_repeat('0', 128),
        ], content: $rawBody)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Webhook signature rejected.']);

        $this->assertDatabaseCount('payment_webhook_deliveries', 0);
    }

    public function test_it_accepts_and_deduplicates_a_signed_transaction_notification(): void
    {
        Queue::fake();
        $binaryKey = $this->configureWebhook();
        $rawBody = $this->payload();
        $server = ['HTTP_X_ANET_SIGNATURE' => $this->signature($rawBody, $binaryKey)];

        $this->call('POST', $this->endpoint(), server: $server, content: $rawBody)
            ->assertOk()
            ->assertExactJson(['data' => ['accepted' => true, 'replayed' => false]]);

        $this->call('POST', $this->endpoint(), server: $server, content: $rawBody)
            ->assertOk()
            ->assertExactJson(['data' => ['accepted' => true, 'replayed' => true]]);

        $this->assertDatabaseCount('payment_webhook_deliveries', 1);
        Queue::assertPushed(ReconcilePaymentWebhookJob::class, 1);
        $delivery = PaymentWebhookDelivery::query()->firstOrFail();
        $stored = DB::table('payment_webhook_deliveries')->first();

        $this->assertSame('private-provider-transaction-id', $delivery->provider_reference);
        $this->assertNotSame('private-provider-transaction-id', $stored->provider_reference);
        $this->assertStringNotContainsString($rawBody, json_encode($stored));
        $this->assertObjectNotHasProperty('raw_body', $stored);
        $this->assertObjectNotHasProperty('signature', $stored);
    }

    public function test_authenticated_but_unsupported_events_are_acknowledged_without_a_job(): void
    {
        Queue::fake();
        $binaryKey = $this->configureWebhook();
        $rawBody = $this->payload('net.authorize.customer.created', 'customerProfile');

        $this->call('POST', $this->endpoint(), server: [
            'HTTP_X_ANET_SIGNATURE' => $this->signature($rawBody, $binaryKey),
        ], content: $rawBody)->assertOk();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('payment_webhook_deliveries', [
            'event_type' => 'net.authorize.customer.created',
            'status' => 'ignored',
            'safe_error_code' => 'unsupported_event',
            'provider_reference' => null,
        ]);
    }

    public function test_it_rejects_a_non_scalar_transaction_identifier_without_persisting_it(): void
    {
        Queue::fake();
        $binaryKey = $this->configureWebhook();
        $rawBody = json_encode([
            'notificationId' => 'd0e8e7fe-c3e7-4add-a480-27bc5ce28a18',
            'eventType' => 'net.authorize.payment.authcapture.created',
            'eventDate' => '2026-08-31T01:00:00Z',
            'payload' => [
                'entityName' => 'transaction',
                'id' => ['unexpected'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', $this->endpoint(), server: [
            'HTTP_X_ANET_SIGNATURE' => $this->signature($rawBody, $binaryKey),
        ], content: $rawBody)
            ->assertBadRequest()
            ->assertExactJson(['message' => 'Webhook payload rejected.']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('payment_webhook_deliveries', 0);
    }

    private function configureWebhook(bool $enabled = true): string
    {
        $binaryKey = random_bytes(64);
        config()->set([
            'payments.provider' => 'authorize_net',
            'payments.authorize_net.environment' => 'sandbox',
            'payments.authorize_net.api_login_id' => 'sandbox-login',
            'payments.authorize_net.transaction_key' => 'private-transaction-key',
            'payments.authorize_net.signature_key' => bin2hex($binaryKey),
            'payments.authorize_net.webhook_enabled' => $enabled,
            'payments.authorize_net.webhook_max_body_bytes' => 65536,
        ]);

        return $binaryKey;
    }

    private function payload(
        string $eventType = 'net.authorize.payment.authcapture.created',
        string $entityName = 'transaction',
    ): string {
        return json_encode([
            'notificationId' => 'd0e8e7fe-c3e7-4add-a480-27bc5ce28a18',
            'eventType' => $eventType,
            'eventDate' => '2026-08-31T01:00:00Z',
            'webhookId' => '63d6fea2-aa13-4b1d-a204-f5fbc15942b7',
            'payload' => [
                'merchantReferenceId' => '00000000-0000-4000',
                'entityName' => $entityName,
                'id' => 'private-provider-transaction-id',
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function signature(string $rawBody, string $binaryKey): string
    {
        return 'sha512='.hash_hmac('sha512', $rawBody, $binaryKey);
    }

    private function endpoint(): string
    {
        return '/api/v1/webhooks/authorize-net';
    }
}
