<?php

namespace Tests\Unit\Domains\Payments;

use App\Domains\Payments\Services\AuthorizeNetWebhookSignatureVerifier;
use Tests\TestCase;

class AuthorizeNetWebhookSignatureVerifierTest extends TestCase
{
    public function test_it_verifies_the_exact_raw_body_with_the_binary_signature_key(): void
    {
        $binaryKey = random_bytes(64);
        config()->set('payments.authorize_net.signature_key', bin2hex($binaryKey));
        $rawBody = '{"notificationId":"delivery-1","payload":{"id":"123"}}';
        $signature = 'sha512='.strtoupper(hash_hmac('sha512', $rawBody, $binaryKey));
        $verifier = app(AuthorizeNetWebhookSignatureVerifier::class);

        $this->assertTrue($verifier->configured());
        $this->assertTrue($verifier->verify($rawBody, $signature));
        $this->assertFalse($verifier->verify($rawBody."\n", $signature));
        $this->assertFalse($verifier->verify($rawBody, 'sha512='.str_repeat('0', 128)));
    }

    public function test_it_fails_closed_for_a_missing_or_malformed_signature_key(): void
    {
        $verifier = app(AuthorizeNetWebhookSignatureVerifier::class);

        config()->set('payments.authorize_net.signature_key', null);
        $this->assertFalse($verifier->configured());
        $this->assertFalse($verifier->verify('{}', null));

        config()->set('payments.authorize_net.signature_key', str_repeat('z', 128));
        $this->assertFalse($verifier->configured());
        $this->assertFalse($verifier->verify('{}', 'sha512='.str_repeat('0', 128)));
    }
}
