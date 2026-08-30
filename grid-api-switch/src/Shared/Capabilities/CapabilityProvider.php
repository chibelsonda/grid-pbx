<?php

declare(strict_types=1);

namespace GridPbx\Switch\Shared\Capabilities;

interface CapabilityProvider
{
    /** @return array{available: bool|null, default: bool|null} */
    public function capability(string $path): array;
}
