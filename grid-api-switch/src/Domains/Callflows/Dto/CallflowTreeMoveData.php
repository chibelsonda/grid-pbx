<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use InvalidArgumentException;

/**
 * Moves one existing callflow subtree without exposing or rebuilding node data.
 *
 * Paths contain only the small public branch vocabulary. Unknown/vendor branch
 * keys therefore remain unreachable and are preserved verbatim.
 */
final readonly class CallflowTreeMoveData
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

    /**
     * @param  array<string, mixed>  $current
     * @param  list<string>  $sourcePath
     * @param  list<string>  $destinationParentPath
     */
    public function __construct(
        private array $current,
        public array $sourcePath,
        public array $destinationParentPath,
        public string $destinationBranch,
    ) {
        if (! is_array($this->current['flow'] ?? null)) {
            throw new InvalidArgumentException('Switch callflow must contain a root flow node before its tree can be edited.');
        }

        if ($this->sourcePath === []) {
            throw new InvalidArgumentException('The Switch callflow root node cannot be moved.');
        }

        $this->assertPublicPath($this->sourcePath, 'source');
        $this->assertPublicPath($this->destinationParentPath, 'destination');

        if (! in_array($this->destinationBranch, self::PUBLIC_BRANCH_KEYS, true)) {
            throw new InvalidArgumentException('The destination branch is not editable.');
        }

        $destinationPath = [...$this->destinationParentPath, $this->destinationBranch];

        if ($destinationPath === $this->sourcePath) {
            throw new InvalidArgumentException('The callflow node is already in the requested branch.');
        }

        if ($this->pathStartsWith($this->destinationParentPath, $this->sourcePath)) {
            throw new InvalidArgumentException('A callflow node cannot be moved into its own subtree.');
        }

        /** @var array<string, mixed> $flow */
        $flow = $this->current['flow'];
        $source = $this->nodeAt($flow, $this->sourcePath, 'source');
        $destination = $this->nodeAt($flow, $this->destinationParentPath, 'destination');

        if (! is_string($source['module'] ?? null)) {
            throw new InvalidArgumentException('The source callflow node is invalid.');
        }

        $destinationModule = is_string($destination['module'] ?? null)
            ? $destination['module']
            : '';

        if (! $this->supportsBranch($destinationModule, $this->destinationBranch)) {
            throw new InvalidArgumentException('The destination branch is not valid for the selected callflow node.');
        }

        $destinationChildren = is_array($destination['children'] ?? null)
            ? $destination['children']
            : [];

        if (array_key_exists($this->destinationBranch, $destinationChildren)) {
            throw new InvalidArgumentException('The destination callflow branch is already occupied.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = $this->withoutPrivateDocumentFields($this->current);
        /** @var array<string, mixed> $flow */
        $flow = $data['flow'];
        $source = $this->removeAt($flow, $this->sourcePath);
        $this->insertAt(
            $flow,
            $this->destinationParentPath,
            $this->destinationBranch,
            $source,
        );
        $data['flow'] = $this->normalizeNodeForJson($flow);

        return $data;
    }

    /** @param list<string> $path */
    private function assertPublicPath(array $path, string $name): void
    {
        foreach ($path as $segment) {
            if (! is_string($segment) || ! in_array($segment, self::PUBLIC_BRANCH_KEYS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'The callflow %s path contains a preserved or unsupported branch.',
                    $name,
                ));
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
     * @param  list<string>  $path
     * @return array<string, mixed>
     */
    private function removeAt(array &$node, array $path): array
    {
        $branch = array_shift($path);

        if (! is_string($branch)) {
            throw new InvalidArgumentException('The source callflow path is invalid.');
        }

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $child = $children[$branch] ?? null;

        if (! is_array($child)) {
            throw new InvalidArgumentException('The source callflow path no longer exists.');
        }

        if ($path === []) {
            unset($children[$branch]);
            $node['children'] = $children;

            return $child;
        }

        $removed = $this->removeAt($child, $path);
        $children[$branch] = $child;
        $node['children'] = $children;

        return $removed;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $parentPath
     * @param  array<string, mixed>  $source
     */
    private function insertAt(
        array &$node,
        array $parentPath,
        string $branch,
        array $source,
    ): void {
        if ($parentPath === []) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $children[$branch] = $source;
            $node['children'] = $children;

            return;
        }

        $segment = array_shift($parentPath);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $child = is_string($segment) ? ($children[$segment] ?? null) : null;

        if (! is_array($child) || ! is_string($segment)) {
            throw new InvalidArgumentException('The destination callflow path no longer exists.');
        }

        $this->insertAt($child, $parentPath, $branch, $source);
        $children[$segment] = $child;
        $node['children'] = $children;
    }

    /** @param list<string> $path */
    private function pathStartsWith(array $path, array $prefix): bool
    {
        return count($path) >= count($prefix)
            && array_slice($path, 0, count($prefix)) === $prefix;
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
