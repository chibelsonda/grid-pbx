<?php

declare(strict_types=1);

namespace GridPbx\Switch\Contracts;

interface TokenProvider
{
    public function token(): string;

    public function invalidate(): void;
}
