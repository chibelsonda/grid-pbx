<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

use InvalidArgumentException;

final readonly class AccountBlacklistsWriteData
{
    /** @param list<string> $blacklistIds */
    public function __construct(public array $blacklistIds)
    {
        foreach ($this->blacklistIds as $id) {
            if (! is_string($id) || $id === '') {
                throw new InvalidArgumentException('Switch blacklist identifiers must be non-empty strings.');
            }
        }
        if (count(array_unique($this->blacklistIds)) !== count($this->blacklistIds)) {
            throw new InvalidArgumentException('Switch blacklist identifiers must be unique.');
        }
    }

    /** @return array{blacklists: list<string>} */
    public function toSwitchData(): array
    {
        return ['blacklists' => array_values($this->blacklistIds)];
    }
}
