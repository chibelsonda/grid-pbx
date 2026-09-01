<?php

namespace Tests\Unit\Domains\CallRouting;

use App\Domains\CallRouting\Services\CallflowHttpsEndpointPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CallflowHttpsEndpointPolicyTest extends TestCase
{
    #[Test]
    public function it_accepts_only_a_bounded_public_https_literal_shape(): void
    {
        $policy = new CallflowHttpsEndpointPolicy;

        $this->assertTrue($policy->allows('https://voice.example.com/pivot'));
        $this->assertTrue($policy->allows('https://8.8.8.8/pivot'));
        $this->assertFalse($policy->allows('http://voice.example.com/pivot'));
        $this->assertFalse($policy->allows('https://voice.example.com:8443/pivot'));
        $this->assertFalse($policy->allows('https://user:secret@voice.example.com/pivot'));
        $this->assertFalse($policy->allows('https://voice.example.com/pivot#private'));
    }

    #[Test]
    public function it_rejects_obvious_local_private_and_ambiguous_targets(): void
    {
        $policy = new CallflowHttpsEndpointPolicy;

        foreach ([
            'https://localhost/pivot',
            'https://localhost./pivot',
            'https://voice.local/pivot',
            'https://voice.internal/pivot',
            'https://metadata.home.arpa/pivot',
            'https://intranet/pivot',
            'https://2130706433/pivot',
            'https://127.0.0.1/pivot',
            'https://10.0.0.1/pivot',
            'https://169.254.169.254/latest/meta-data',
            'https://[::1]/pivot',
            'https://[fe80::1]/pivot',
        ] as $url) {
            $this->assertFalse($policy->allows($url), $url);
        }
    }
}
