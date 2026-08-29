<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

final readonly class AccountPreflowData
{
    public function __construct(public ?string $always = null) {}

    /** @return array{always: string} */
    public function toSwitchData(): array
    {
        return ['always' => $this->always ?? ''];
    }
}
