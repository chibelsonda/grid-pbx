<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\Routing;

final readonly class UserDialPlanData
{
    /**
     * @param  list<string>  $system
     * @param  list<UserDialPlanRuleData>  $rules
     */
    public function __construct(
        public array $system = [],
        public array $rules = [],
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = ['system' => $this->system];

        foreach ($this->rules as $rule) {
            $data[$rule->pattern] = $rule->options();
        }

        return $data;
    }
}
