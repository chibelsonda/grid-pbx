<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use InvalidArgumentException;

final readonly class CallflowCreateData
{
    private const MENU_BRANCH_KEYS = [
        'timeout',
        '0',
        '1',
        '2',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
        '*',
    ];

    private const SUPPORTED_DESTINATION_MODULES = [
        'user',
        'device',
        'voicemail',
        'callflow',
        'play',
        'directory',
        'group',
        'acdc_member',
        'menu',
        'conference',
        'faxbox',
        'temporal_route',
    ];

    /**
     * @param  list<string>  $phoneNumbers
     * @param  list<CallflowBranchWriteData>  $branchRoutes
     */
    public function __construct(
        public string $name,
        public string $destinationModule,
        public string $destinationResourceId,
        public array $phoneNumbers,
        public ?string $fallbackModule = null,
        public ?string $fallbackResourceId = null,
        public array $branchRoutes = [],
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch callflow name is required.');
        }

        if (! in_array($this->destinationModule, self::SUPPORTED_DESTINATION_MODULES, true)) {
            throw new InvalidArgumentException('Unsupported Switch callflow destination module.');
        }

        if (trim($this->destinationResourceId) === '') {
            throw new InvalidArgumentException('Switch callflow destination identifier is required.');
        }

        if ($this->phoneNumbers === []) {
            throw new InvalidArgumentException('A guided Switch callflow requires at least one phone number.');
        }

        if (($this->fallbackModule === null) !== ($this->fallbackResourceId === null)) {
            throw new InvalidArgumentException('Switch callflow fallback module and identifier must be provided together.');
        }

        if ($this->fallbackModule !== null
            && ! in_array($this->fallbackModule, self::SUPPORTED_DESTINATION_MODULES, true)) {
            throw new InvalidArgumentException('Unsupported Switch callflow fallback module.');
        }

        if ($this->fallbackResourceId !== null && trim($this->fallbackResourceId) === '') {
            throw new InvalidArgumentException('Switch callflow fallback identifier is required.');
        }

        $keys = [];

        foreach ($this->branchRoutes as $branch) {
            if (! $branch instanceof CallflowBranchWriteData || $branch->clearsBranch()) {
                throw new InvalidArgumentException('Switch callflow creation requires complete branch routes.');
            }

            if (in_array($branch->key, $keys, true)) {
                throw new InvalidArgumentException('Switch callflow branch keys must be unique.');
            }

            if (! in_array($branch->module, self::SUPPORTED_DESTINATION_MODULES, true)) {
                throw new InvalidArgumentException('Unsupported Switch callflow branch module.');
            }

            if (! $this->supportsBranchKey($branch->key)) {
                throw new InvalidArgumentException('Switch callflow branch key is not valid for the root module.');
            }

            $keys[] = $branch->key;
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $children = [];

        if ($this->fallbackModule !== null && $this->fallbackResourceId !== null) {
            $children['_'] = [
                'module' => $this->fallbackModule,
                'data' => $this->destinationData($this->fallbackModule, $this->fallbackResourceId),
                'children' => (object) [],
            ];
        }

        foreach ($this->branchRoutes as $branch) {
            $children[$branch->key] = [
                'module' => $branch->module,
                'data' => $this->destinationData($branch->module, $branch->resourceId),
                'children' => (object) [],
            ];
        }

        return [
            'name' => trim($this->name),
            'numbers' => array_values(array_unique($this->phoneNumbers)),
            'flow' => [
                'module' => $this->destinationModule,
                'data' => $this->destinationData($this->destinationModule, $this->destinationResourceId),
                'children' => (object) $children,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function destinationData(string $module, string $resourceId): array
    {
        return $module === 'temporal_route' ? ['rule_set' => $resourceId] : ['id' => $resourceId];
    }

    private function supportsBranchKey(string $key): bool
    {
        return match ($this->destinationModule) {
            'menu' => in_array($key, self::MENU_BRANCH_KEYS, true),
            'temporal_route' => $key === 'rule_set',
            default => false,
        };
    }
}
