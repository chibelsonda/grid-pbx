<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

final readonly class AccountEnabledWriteData
{
    public function __construct(public bool $enabled) {}

    /** @return array{enabled: bool} */
    public function toSwitchData(): array
    {
        return ['enabled' => $this->enabled];
    }
}
