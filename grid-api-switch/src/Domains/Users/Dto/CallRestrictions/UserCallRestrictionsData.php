<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\CallRestrictions;

use stdClass;

final readonly class UserCallRestrictionsData
{
    /**
     * @param  array<string, string>  $actions
     * @param  array<string, array<string, mixed>>  $preservedOptions
     */
    public function __construct(
        public array $actions,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, array<string, mixed>>|stdClass */
    public function toSwitchData(): array|stdClass
    {
        $restrictions = [];

        foreach ($this->actions as $classification => $action) {
            $restrictions[$classification] = array_merge(
                $this->preservedOptions[$classification] ?? [],
                ['action' => $action],
            );
        }

        return $restrictions === [] ? new stdClass : $restrictions;
    }
}
