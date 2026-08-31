<?php

namespace Tests\Unit\Domains\Billing;

use App\Domains\Billing\Services\MySqlLegacyBillingReadOnlyGrantInspector;
use Illuminate\Database\ConnectionInterface;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MySqlLegacyBillingReadOnlyGrantInspectorTest extends TestCase
{
    public function test_it_accepts_only_select_and_metadata_grants(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')
            ->once()
            ->with('SHOW GRANTS FOR CURRENT_USER')
            ->andReturn([
                (object) ['grant' => 'GRANT USAGE ON *.* TO `reader`@`%`'],
                (object) ['grant' => 'GRANT SELECT, SHOW VIEW ON `billing`.* TO `reader`@`%`'],
            ]);

        $this->assertTrue(
            (new MySqlLegacyBillingReadOnlyGrantInspector)->isReadOnly($connection),
        );
    }

    #[DataProvider('unsafeGrants')]
    public function test_it_rejects_write_or_escalation_privileges(string $grant): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')->once()->andReturn([(object) ['grant' => $grant]]);

        $this->assertFalse(
            (new MySqlLegacyBillingReadOnlyGrantInspector)->isReadOnly($connection),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeGrants(): iterable
    {
        yield 'write access' => ['GRANT SELECT, UPDATE ON `billing`.* TO `reader`@`%`'];
        yield 'all privileges' => ['GRANT ALL PRIVILEGES ON `billing`.* TO `reader`@`%`'];
        yield 'grant option' => ['GRANT SELECT ON `billing`.* TO `reader`@`%` WITH GRANT OPTION'];
        yield 'usage without select' => ['GRANT USAGE ON *.* TO `reader`@`%`'];
        yield 'malformed metadata' => ['not a grant'];
    }
}
