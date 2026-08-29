<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use InvalidArgumentException;

final readonly class CallflowWriteData
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
     * @param  array<string, mixed>  $current
     * @param  list<string>|null  $assignedPhoneNumbers
     * @param  list<string>  $knownPhoneNumbers
     * @param  list<CallflowBranchWriteData>  $branchOperations
     */
    public function __construct(
        private array $current,
        public string $destinationModule,
        public string $destinationResourceId,
        public ?string $name = null,
        private ?array $assignedPhoneNumbers = null,
        private array $knownPhoneNumbers = [],
        private bool $replaceFallback = false,
        private ?string $fallbackModule = null,
        private ?string $fallbackResourceId = null,
        private array $branchOperations = [],
    ) {
        if (! in_array($this->destinationModule, self::SUPPORTED_DESTINATION_MODULES, true)) {
            throw new InvalidArgumentException('Unsupported Switch callflow destination module.');
        }

        if (trim($this->destinationResourceId) === '') {
            throw new InvalidArgumentException('Switch callflow destination identifier is required.');
        }

        if (! is_array($this->current['flow'] ?? null)) {
            throw new InvalidArgumentException('Switch callflow must contain a root flow node before it can be edited.');
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

        foreach ($this->branchOperations as $branch) {
            if (! $branch instanceof CallflowBranchWriteData) {
                throw new InvalidArgumentException('Invalid Switch callflow branch operation.');
            }

            if (in_array($branch->key, $keys, true)) {
                throw new InvalidArgumentException('Switch callflow branch operation keys must be unique.');
            }

            if ($branch->module !== null
                && ! in_array($branch->module, self::SUPPORTED_DESTINATION_MODULES, true)) {
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
        $data = $this->current;

        foreach (array_keys($data) as $key) {
            if (
                $key === 'id'
                || $key === '_id'
                || $key === '_rev'
                || $key === 'account_id'
                || $key === 'created'
                || $key === 'modified'
                || str_starts_with($key, 'pvt_')
            ) {
                unset($data[$key]);
            }
        }

        if ($this->name !== null) {
            $data['name'] = trim($this->name);
        }

        /** @var array<string, mixed> $flow */
        $flow = $data['flow'];
        $currentModule = is_string($flow['module'] ?? null) ? $flow['module'] : null;
        $destinationData = $currentModule === $this->destinationModule
            && is_array($flow['data'] ?? null)
                ? $flow['data']
                : [];

        $flow['module'] = $this->destinationModule;
        if ($this->destinationModule === 'temporal_route') {
            unset($destinationData['id']);
            $destinationData['rule_set'] = $this->destinationResourceId;
        } else {
            unset($destinationData['rule_set']);
            $destinationData['id'] = $this->destinationResourceId;
        }
        $flow['data'] = $destinationData;
        $flow['children'] = is_array($flow['children'] ?? null) ? $flow['children'] : [];

        if ($this->replaceFallback) {
            if ($this->fallbackModule === null || $this->fallbackResourceId === null) {
                unset($flow['children']['_']);
            } else {
                $currentFallback = is_array($flow['children']['_'] ?? null)
                    ? $flow['children']['_']
                    : [];
                $currentFallbackModule = is_string($currentFallback['module'] ?? null)
                    ? $currentFallback['module']
                    : null;
                $fallbackData = $currentFallbackModule === $this->fallbackModule
                    && is_array($currentFallback['data'] ?? null)
                        ? $currentFallback['data']
                        : [];

                if ($this->fallbackModule === 'temporal_route') {
                    unset($fallbackData['id']);
                    $fallbackData['rule_set'] = $this->fallbackResourceId;
                } else {
                    unset($fallbackData['rule_set']);
                    $fallbackData['id'] = $this->fallbackResourceId;
                }

                $flow['children']['_'] = [
                    'module' => $this->fallbackModule,
                    'data' => $fallbackData,
                    'children' => is_array($currentFallback['children'] ?? null)
                        ? $currentFallback['children']
                        : [],
                ];
            }
        }

        foreach ($this->branchOperations as $branch) {
            if ($branch->clearsBranch()) {
                unset($flow['children'][$branch->key]);

                continue;
            }

            $currentBranch = is_array($flow['children'][$branch->key] ?? null)
                ? $flow['children'][$branch->key]
                : [];
            $currentBranchModule = is_string($currentBranch['module'] ?? null)
                ? $currentBranch['module']
                : null;
            $branchData = $currentBranchModule === $branch->module
                && is_array($currentBranch['data'] ?? null)
                    ? $currentBranch['data']
                    : [];

            if ($branch->module === 'temporal_route') {
                unset($branchData['id']);
                $branchData['rule_set'] = $branch->resourceId;
            } else {
                unset($branchData['rule_set']);
                $branchData['id'] = $branch->resourceId;
            }

            $flow['children'][$branch->key] = [
                'module' => $branch->module,
                'data' => $branchData,
                'children' => is_array($currentBranch['children'] ?? null)
                    ? $currentBranch['children']
                    : [],
            ];
        }

        $data['flow'] = $this->normalizeNodeForJson($flow);

        if ($this->assignedPhoneNumbers !== null) {
            $currentNumbers = is_array($data['numbers'] ?? null) ? $data['numbers'] : [];
            $preservedNumbers = array_values(array_filter(
                $currentNumbers,
                fn (mixed $number): bool => is_string($number)
                    && $number !== ''
                    && ! in_array($number, $this->knownPhoneNumbers, true),
            ));
            $data['numbers'] = array_values(array_unique([
                ...$preservedNumbers,
                ...$this->assignedPhoneNumbers,
            ]));
        }

        return $data;
    }

    /**
     * Preserve numeric DTMF branch names as JSON object properties rather than
     * allowing PHP's numeric array keys to be encoded as a JSON list.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function normalizeNodeForJson(array $node): array
    {
        $children = [];

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $key => $child) {
            if ((is_string($key) || is_int($key)) && is_array($child)) {
                $children[(string) $key] = $this->normalizeNodeForJson($child);
            }
        }

        $node['children'] = (object) $children;

        return $node;
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
