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

    private const SUPPORTED_INLINE_ROOT_MODULES = [
        'ring_group',
    ];

    /**
     * @param  list<string>  $entryNumbers
     * @param  list<CallflowBranchWriteData>  $branchRoutes
     * @param  list<string>  $destinationTemporalRuleIds
     * @param  array<string, mixed>|null  $destinationSettings
     */
    public function __construct(
        public string $name,
        public string $destinationModule,
        public ?string $destinationResourceId,
        public array $entryNumbers,
        public ?string $fallbackModule = null,
        public ?string $fallbackResourceId = null,
        public array $branchRoutes = [],
        public array $destinationTemporalRuleIds = [],
        public ?array $destinationSettings = null,
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch callflow name is required.');
        }

        if (! in_array($this->destinationModule, [
            ...self::SUPPORTED_DESTINATION_MODULES,
            ...self::SUPPORTED_INLINE_ROOT_MODULES,
        ], true)) {
            throw new InvalidArgumentException('Unsupported Switch callflow destination module.');
        }

        $this->assertDestinationConfiguration();

        if ($this->entryNumbers === []) {
            throw new InvalidArgumentException('A guided Switch callflow requires at least one entry number.');
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
            'numbers' => array_values(array_unique($this->entryNumbers)),
            'flow' => [
                'module' => $this->destinationModule,
                'data' => $this->rootDestinationData(),
                'children' => (object) $children,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function destinationData(string $module, string $resourceId): array
    {
        return $module === 'temporal_route' ? ['rule_set' => $resourceId] : ['id' => $resourceId];
    }

    /** @return array<string, mixed> */
    private function rootDestinationData(): array
    {
        if (in_array($this->destinationModule, self::SUPPORTED_INLINE_ROOT_MODULES, true)) {
            return CallflowInlineNodeWriteData::rootNode(
                $this->destinationModule,
                $this->destinationSettings ?? [],
            )['data'];
        }

        if ($this->destinationTemporalRuleIds !== []) {
            return ['rules' => array_values($this->destinationTemporalRuleIds)];
        }

        $data = $this->destinationData(
            $this->destinationModule,
            (string) $this->destinationResourceId,
        );

        if ($this->destinationSettings !== null) {
            $data['timeout'] = $this->destinationSettings['timeout'];
            $data['can_call_self'] = $this->destinationSettings['can_call_self'];
        }

        return $data;
    }

    private function assertDestinationConfiguration(): void
    {
        if (in_array($this->destinationModule, self::SUPPORTED_INLINE_ROOT_MODULES, true)) {
            if ($this->destinationResourceId !== null
                || $this->destinationTemporalRuleIds !== []
                || $this->destinationSettings === null) {
                throw new InvalidArgumentException('Inline callflow roots require settings without a destination identifier.');
            }

            CallflowInlineNodeWriteData::rootNode(
                $this->destinationModule,
                $this->destinationSettings,
            );

            return;
        }

        if ($this->destinationSettings !== null) {
            if (! in_array($this->destinationModule, ['user', 'device'], true)) {
                throw new InvalidArgumentException('This resource-backed callflow destination cannot contain settings.');
            }

            $this->assertEndpointSettings($this->destinationSettings);
        }

        if ($this->destinationTemporalRuleIds !== []) {
            if ($this->destinationModule !== 'temporal_route' || $this->destinationResourceId !== null) {
                throw new InvalidArgumentException('Direct temporal rules require a temporal-route destination without a rule-set identifier.');
            }

            foreach ($this->destinationTemporalRuleIds as $ruleId) {
                if (! is_string($ruleId) || trim($ruleId) === '') {
                    throw new InvalidArgumentException('Direct temporal-rule identifiers must be non-empty strings.');
                }
            }

            if (count(array_unique($this->destinationTemporalRuleIds)) !== count($this->destinationTemporalRuleIds)) {
                throw new InvalidArgumentException('Direct temporal rules must be unique.');
            }

            return;
        }

        if ($this->destinationResourceId === null || trim($this->destinationResourceId) === '') {
            throw new InvalidArgumentException('Switch callflow destination identifier is required.');
        }
    }

    /** @param array<string, mixed> $settings */
    private function assertEndpointSettings(array $settings): void
    {
        if (count($settings) !== 2
            || ! array_key_exists('timeout', $settings)
            || ! array_key_exists('can_call_self', $settings)
            || ! is_int($settings['timeout'])
            || $settings['timeout'] < 1
            || $settings['timeout'] > 600
            || ! is_bool($settings['can_call_self'])) {
            throw new InvalidArgumentException('The guided endpoint action settings are invalid.');
        }
    }

    private function supportsBranchKey(string $key): bool
    {
        return match ($this->destinationModule) {
            'menu' => in_array($key, self::MENU_BRANCH_KEYS, true),
            'temporal_route' => $key === 'rule_set'
                || in_array($key, $this->destinationTemporalRuleIds, true),
            default => false,
        };
    }
}
