<?php

namespace Tests\Feature\Domains\Payments;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentOperation;
use App\Domains\Payments\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class PaymentAttemptModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attempt_uses_public_uuid_and_keeps_sensitive_fields_out_of_serialization(): void
    {
        $account = SwitchAccount::factory()->create();
        $attempt = PaymentAttempt::query()->create([
            'switch_account_id' => $account->getKey(),
            'provider' => 'authorize_net',
            'operation' => PaymentOperation::Charge,
            'idempotency_hash' => hash('sha256', 'idempotency-key'),
            'request_fingerprint' => hash('sha256', 'request'),
            'amount' => '12.34000000',
            'currency' => 'USD',
            'status' => PaymentAttemptStatus::Pending,
            'provider_reference' => 'private-provider-reference',
            'provider_reference_hash' => hash('sha256', 'private-provider-reference'),
        ]);
        $event = $attempt->events()->create([
            'event_type' => 'attempt_created',
            'status' => PaymentAttemptStatus::Pending,
            'safe_context' => ['source' => 'server'],
        ]);

        $serialized = $attempt->fresh()->toArray();

        $this->assertNotEmpty($attempt->id);
        $this->assertArrayNotHasKey('payment_attempt_id', $serialized);
        $this->assertArrayNotHasKey('switch_account_id', $serialized);
        $this->assertArrayNotHasKey('idempotency_hash', $serialized);
        $this->assertArrayNotHasKey('request_fingerprint', $serialized);
        $this->assertArrayNotHasKey('provider_reference', $serialized);
        $this->assertDatabaseMissing('payment_attempts', [
            'payment_attempt_id' => $attempt->getKey(),
            'provider_reference' => 'private-provider-reference',
        ]);

        $this->expectException(LogicException::class);
        $event->update(['event_type' => 'changed']);
    }
}
