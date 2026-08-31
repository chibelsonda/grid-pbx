<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\LegacyBillingReadOnlyGrantInspector;
use Illuminate\Database\ConnectionInterface;

final class MySqlLegacyBillingReadOnlyGrantInspector implements LegacyBillingReadOnlyGrantInspector
{
    /** @var list<string> */
    private const ALLOWED_PRIVILEGES = ['SELECT', 'SHOW VIEW', 'USAGE'];

    public function isReadOnly(ConnectionInterface $connection): bool
    {
        $hasSelect = false;

        foreach ($connection->select('SHOW GRANTS FOR CURRENT_USER') as $row) {
            $grant = $this->grantText($row);

            if ($grant === null || str_contains(strtoupper($grant), ' WITH GRANT OPTION')) {
                return false;
            }

            if (! preg_match('/^GRANT\s+(.+?)\s+ON\s+/i', $grant, $matches)) {
                return false;
            }

            $privileges = array_map(
                static fn (string $privilege): string => strtoupper(trim($privilege)),
                explode(',', $matches[1]),
            );

            foreach ($privileges as $privilege) {
                if (! in_array($privilege, self::ALLOWED_PRIVILEGES, true)) {
                    return false;
                }

                $hasSelect = $hasSelect || $privilege === 'SELECT';
            }
        }

        return $hasSelect;
    }

    private function grantText(mixed $row): ?string
    {
        if (! is_object($row) && ! is_array($row)) {
            return null;
        }

        foreach ((array) $row as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
