<?php

declare(strict_types=1);

namespace GridPbx\Kazoo\Contracts;

interface TokenProvider
{
    public function token(): string;

    public function invalidate(): void;
}
