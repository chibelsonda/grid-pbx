<?php

namespace Tests\Unit\Domains\Dashboard;

use App\Domains\Dashboard\Services\CallGeographyNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CallGeographyNumberNormalizerTest extends TestCase
{
    #[DataProvider('numberCases')]
    public function test_accepts_only_unambiguous_international_numbers(
        ?string $input,
        ?string $expected,
    ): void {
        $this->assertSame($expected, (new CallGeographyNumberNormalizer)->normalize($input));
    }

    /** @return iterable<string, array{?string, ?string}> */
    public static function numberCases(): iterable
    {
        yield 'formatted e164' => ['+1 (415) 555-0100', '+14155550100'];
        yield 'international access prefix' => ['0044 20 7946 0018', '+442079460018'];
        yield 'tel uri' => ['tel:+61-2-9374-4000', '+61293744000'];
        yield 'sip uri' => ['sip:+33142345678@example.test', '+33142345678'];
        yield 'internal extension' => ['1001', null];
        yield 'anonymous' => ['anonymous', null];
        yield 'ambiguous national number' => ['4155550100', null];
        yield 'too long' => ['+1234567890123456', null];
        yield 'empty' => ['', null];
        yield 'missing' => [null, null];
    }
}
