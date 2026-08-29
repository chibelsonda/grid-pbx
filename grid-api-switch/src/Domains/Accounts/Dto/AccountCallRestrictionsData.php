<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

final readonly class AccountCallRestrictionsData
{
    /** @param array<string, string> $actions */
    public function __construct(public array $actions) {}

    /** @return array<string, array{action: string}> */
    public function toSwitchData(): array
    {
        $restrictions = [];

        foreach ($this->actions as $classification => $action) {
            $restrictions[$classification] = ['action' => $action];
        }

        return $restrictions;
    }
}
