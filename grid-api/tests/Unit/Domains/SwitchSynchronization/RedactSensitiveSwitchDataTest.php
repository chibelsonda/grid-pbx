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
            'auth_token' => 'transport-secret',
            'caller_id' => ['internal' => ['number' => '1001']],
        ];

        $redacted = (new RedactSensitiveSwitchData)->handle($payload);

        $this->assertSame('device-1', $redacted['id']);
        $this->assertSame('device-1', $redacted['sip']['username']);
        $this->assertSame('[REDACTED]', $redacted['sip']['password']);
        $this->assertSame('[REDACTED]', $redacted['hotdesk']['hotdesk_pin']);
        $this->assertTrue($redacted['hotdesk']['enabled']);
        $this->assertSame('[REDACTED]', $redacted['auth_token']);
        $this->assertSame('1001', $redacted['caller_id']['internal']['number']);
    }
}
