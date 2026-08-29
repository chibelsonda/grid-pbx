<?php

declare(strict_types=1);

namespace GridPbx\Switch\Shared\Authentication;

interface TokenProvider
{
    public function token(): string;

    public function invalidate(): void;
}
