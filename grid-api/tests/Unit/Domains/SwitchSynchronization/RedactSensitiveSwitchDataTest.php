<?php

namespace Tests\Unit\Domains\SwitchSynchronization;

use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Tests\TestCase;

class RedactSensitiveSwitchDataTest extends TestCase
{
    public function test_it_recursively_redacts_credentials_without_discarding_other_payload_fields(): void
    {
        $payload = [
            'id' => 'device-1',
            'sip' => [
                'username' => 'device-1',
                'password' => 'sip-secret',
            ],
            'hotdesk' => [
                'hotdesk_pin' => 1234,
                'enabled' => true,
            ],
            'member' => ['pins' => ['1234', '5678']],
            'auth_token' => 'transport-secret',
            'payment_tokens' => [['id' => 'payment-secret']],
            'bookkeeper' => ['type' => 'private-provider'],
            'bookkeepers' => ['local' => ['id' => 'private-provider']],
            'billing_id' => 'billing-account-secret',
            'caller_id' => ['internal' => ['number' => '1001']],
        ];

        $redacted = (new RedactSensitiveSwitchData)->handle($payload);

        $this->assertSame('device-1', $redacted['id']);
        $this->assertSame('device-1', $redacted['sip']['username']);
        $this->assertSame('[REDACTED]', $redacted['sip']['password']);
        $this->assertSame('[REDACTED]', $redacted['hotdesk']['hotdesk_pin']);
        $this->assertSame('[REDACTED]', $redacted['member']['pins']);
        $this->assertTrue($redacted['hotdesk']['enabled']);
        $this->assertSame('[REDACTED]', $redacted['auth_token']);
        $this->assertSame('[REDACTED]', $redacted['payment_tokens']);
        $this->assertSame('[REDACTED]', $redacted['bookkeeper']);
        $this->assertSame('[REDACTED]', $redacted['bookkeepers']);
        $this->assertSame('[REDACTED]', $redacted['billing_id']);
        $this->assertSame('1001', $redacted['caller_id']['internal']['number']);
    }
}
