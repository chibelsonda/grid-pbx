<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use InvalidArgumentException;

/**
 * Adds or retargets one guided reference node without rebuilding its subtree.
 */
final readonly class CallflowTreeNodeWriteData
{
    private const PUBLIC_BRANCH_KEYS = [
        '_',
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
        'rule_set',
    ];

    private const SUPPORTED_MODULES = [
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
     * @param  list<string>  $nodePath
     * @param  list<string>  $parentPath
     */
    private function __construct(
        private array $current,
        private string $operation,
        public array $nodePath,
        public array $parentPath,
        public ?string $branch,
        public string $module,
        public string $resourceId,
    ) {
        if (! is_array($this->current['flow'] ?? null)) {
            throw new InvalidArgumentException('Switch callflow must contain a root flow node before its tree can be edited.');
        }

        if (! in_array($this->module, self::SUPPORTED_MODULES, true)) {
            throw new InvalidArgumentException('The Switch callflow action is not supported by the guided tree editor.');
        }

        if (trim($this->resourceId) === '') {
            throw new InvalidArgumentException('The Switch callflow action identifier is required.');
        }

        $this->assertPublicPath($this->nodePath);
        $this->assertPublicPath($this->parentPath);

        /** @var array<string, mixed> $flow */
        $flow = $this->current['flow'];

        if ($this->operation === 'create') {
            $this->assertCreate($flow);

            return;
        }

        $this->assertUpdate($flow);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  list<string>  $parentPath
     */
    public static function create(
        array $current,
        array $parentPath,
        string $branch,
        string $module,
        string $resourceId,
    ): self {
        return new self($current, 'create', [], $parentPath, $branch, $module, $resourceId);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  list<string>  $nodePath
     */
    public static function update(
        array $current,
        array $nodePath,
        string $module,
        string $resourceId,
    ): self {
        return new self($current, 'update', $nodePath, [], null, $module, $resourceId);
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = $this->withoutPrivateDocumentFields($this->current);
        /** @var array<string, mixed> $flow */
        $flow = $data['flow'];

        if ($this->operation === 'create' && $this->branch !== null) {
            $this->insertAt($flow, $this->parentPath, $this->branch);
        } else {
            $this->updateAt($flow, $this->nodePath);
        }

        $data['flow'] = $this->normalizeNodeForJson($flow);

        return $data;
    }

    /** @param array<string, mixed> $flow */
    private function assertCreate(array $flow): void
    {
        if ($this->branch === null || ! in_array($this->branch, self::PUBLIC_BRANCH_KEYS, true)) {
            throw new InvalidArgumentException('The destination branch is not editable.');
        }

        $parent = $this->nodeAt($flow, $this->parentPath, 'parent');
        $parentModule = is_string($parent['module'] ?? null) ? $parent['module'] : '';

        if (! $this->supportsBranch($parentModule, $this->branch)) {
            throw new InvalidArgumentException('The destination branch is not valid for the selected callflow node.');
        }

        $children = is_array($parent['children'] ?? null) ? $parent['children'] : [];

        if (array_key_exists($this->branch, $children)) {
            throw new InvalidArgumentException('The destination callflow branch is already occupied.');
        }
    }

    /** @param array<string, mixed> $flow */
    private function assertUpdate(array $flow): void
    {
        if ($this->nodePath === []) {
            throw new InvalidArgumentException('The root callflow action must be edited through the route editor.');
        }

        $node = $this->nodeAt($flow, $this->nodePath, 'node');

        if (($node['module'] ?? null) !== $this->module) {
            throw new InvalidArgumentException('The selected callflow action module changed and must be reloaded.');
        }
    }

    /** @param list<string> $path */
    private function assertPublicPath(array $path): void
    {
        foreach ($path as $segment) {
            if (! is_string($segment) || ! in_array($segment, self::PUBLIC_BRANCH_KEYS, true)) {
                throw new InvalidArgumentException('The callflow path contains a preserved or unsupported branch.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $path
     * @return array<string, mixed>
     */
    private function nodeAt(array $node, array $path, string $name): array
    {
        foreach ($path as $segment) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $child = $children[$segment] ?? null;

            if (! is_array($child)) {
                throw new InvalidArgumentException(sprintf('The callflow %s path no longer exists.', $name));
            }

            $node = $child;
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $parentPath
     */
    private function insertAt(array &$node, array $parentPath, string $branch): void
    {
        if ($parentPath === []) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $children[$branch] = $this->newNode();
            $node['children'] = $children;

            return;
        }

        $segment = array_shift($parentPath);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $child = is_string($segment) ? ($children[$segment] ?? null) : null;

        if (! is_array($child) || ! is_string($segment)) {
            throw new InvalidArgumentException('The destination callflow path no longer exists.');
        }

        $this->insertAt($child, $parentPath, $branch);
        $children[$segment] = $child;
        $node['children'] = $children;
    }

    /** @param array<string, mixed> $node @param list<string> $path */
    private function updateAt(array &$node, array $path): void
    {
        $segment = array_shift($path);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $child = is_string($segment) ? ($children[$segment] ?? null) : null;

        if (! is_array($child) || ! is_string($segment)) {
            throw new InvalidArgumentException('The selected callflow path no longer exists.');
        }

        if ($path === []) {
            $data = is_array($child['data'] ?? null) ? $child['data'] : [];
            $child['data'] = $this->destinationData($data);
            $children[$segment] = $child;
            $node['children'] = $children;

            return;
        }

        $this->updateAt($child, $path);
        $children[$segment] = $child;
        $node['children'] = $children;
    }

    /** @return array{module: string, data: array<string, mixed>, children: object} */
    private function newNode(): array
    {
        return [
            'module' => $this->module,
            'data' => $this->destinationData([]),
            'children' => (object) [],
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function destinationData(array $current): array
    {
        if ($this->module === 'temporal_route') {
            unset($current['id'], $current['rules']);
            $current['rule_set'] = $this->resourceId;
        } else {
            unset($current['rule_set'], $current['rules']);
            $current['id'] = $this->resourceId;
        }

        return $current;
    }

    private function supportsBranch(string $module, string $branch): bool
    {
        if ($branch === '_') {
            return true;
        }

        if ($module === 'menu') {
            return in_array($branch, ['timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*'], true);
        }

        return $module === 'temporal_route' && $branch === 'rule_set';
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function withoutPrivateDocumentFields(array $data): array
    {
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

        return $data;
    }

    /** @param array<string, mixed> $node @return array<string, mixed> */
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
}
