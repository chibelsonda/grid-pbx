<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use GridPbx\Switch\Domains\Callflows\Support\CallflowBranchPolicy;
use InvalidArgumentException;

/**
 * Reorders complete public subtrees without exposing or rebuilding node data.
 */
final readonly class CallflowTreeReorderData
{
    private const MODES = ['insert_before', 'swap'];

    /**
     * @param  array<string, mixed>  $current
     * @param  list<string>  $sourcePath
     * @param  list<string>  $targetPath
     */
    public function __construct(
        private array $current,
        public string $mode,
        public array $sourcePath,
        public array $targetPath,
    ) {
        if (! in_array($this->mode, self::MODES, true)) {
            throw new InvalidArgumentException('The callflow reorder mode is not supported.');
        }

        if (! is_array($this->current['flow'] ?? null)) {
            throw new InvalidArgumentException('Switch callflow must contain a root flow node before its tree can be reordered.');
        }

        if ($this->sourcePath === [] || $this->targetPath === []) {
            throw new InvalidArgumentException('The root callflow node cannot be reordered.');
        }

        $this->assertPublicPath($this->sourcePath);
        $this->assertPublicPath($this->targetPath);

        if ($this->sourcePath === $this->targetPath) {
            throw new InvalidArgumentException('Select two different callflow positions to reorder.');
        }

        /** @var array<string, mixed> $flow */
        $flow = $this->current['flow'];
        $source = $this->nodeAt($flow, $this->sourcePath, 'source');
        $this->nodeAt($flow, $this->targetPath, 'target');

        if ($this->pathStartsWith($this->targetPath, $this->sourcePath)) {
            throw new InvalidArgumentException('A callflow action cannot be reordered into its own subtree.');
        }

        if ($this->mode === 'swap' && $this->pathStartsWith($this->sourcePath, $this->targetPath)) {
            throw new InvalidArgumentException('Ancestor and descendant callflow actions cannot be swapped.');
        }

        $sourceChildren = is_array($source['children'] ?? null) ? $source['children'] : [];

        if ($this->mode === 'insert_before' && array_key_exists('_', $sourceChildren)) {
            throw new InvalidArgumentException('Insert-before requires an empty default continuation on the moving action.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = $this->withoutPrivateDocumentFields($this->current);
        /** @var array<string, mixed> $flow */
        $flow = $data['flow'];

        if ($this->mode === 'swap') {
            $source = $this->nodeAt($flow, $this->sourcePath, 'source');
            $target = $this->nodeAt($flow, $this->targetPath, 'target');
            $this->replaceAt($flow, $this->sourcePath, $target);
            $this->replaceAt($flow, $this->targetPath, $source);
        } else {
            $source = $this->removeAt($flow, $this->sourcePath);
            $target = $this->removeAt($flow, $this->targetPath);
            $sourceChildren = is_array($source['children'] ?? null) ? $source['children'] : [];
            $sourceChildren['_'] = $target;
            $source['children'] = $sourceChildren;
            $this->insertAt($flow, $this->targetPath, $source);
        }

        $data['flow'] = $this->normalizeNodeForJson($flow);

        return $data;
    }

    /** @param list<string> $path */
    private function assertPublicPath(array $path): void
    {
        foreach ($path as $segment) {
            if (! is_string($segment) || ! CallflowBranchPolicy::isPublicKey($segment)) {
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
            if (! is_string($segment) || ! CallflowBranchPolicy::supports($node, $segment)) {
                throw new InvalidArgumentException(sprintf('The callflow %s path contains a preserved branch.', $name));
            }

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $child = $children[$segment] ?? null;

            if (! is_array($child)) {
                throw new InvalidArgumentException(sprintf('The callflow %s path no longer exists.', $name));
            }

            $node = $child;
        }

        return $node;
    }

    /** @param array<string, mixed> $node @param list<string> $path @return array<string, mixed> */
    private function removeAt(array &$node, array $path): array
    {
        $branch = array_shift($path);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $child = is_string($branch) ? ($children[$branch] ?? null) : null;

        if (! is_array($child) || ! is_string($branch)) {
            throw new InvalidArgumentException('The callflow reorder path no longer exists.');
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

    /** @param array<string, mixed> $node @param list<string> $path @param array<string, mixed> $replacement */
    private function replaceAt(array &$node, array $path, array $replacement): void
    {
        $branch = array_shift($path);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $child = is_string($branch) ? ($children[$branch] ?? null) : null;

        if (! is_array($child) || ! is_string($branch)) {
            throw new InvalidArgumentException('The callflow reorder path no longer exists.');
        }

        if ($path === []) {
            $children[$branch] = $replacement;
        } else {
            $this->replaceAt($child, $path, $replacement);
            $children[$branch] = $child;
        }

        $node['children'] = $children;
    }

    /** @param array<string, mixed> $node @param list<string> $path @param array<string, mixed> $inserted */
    private function insertAt(array &$node, array $path, array $inserted): void
    {
        $branch = array_shift($path);

        if (! is_string($branch)) {
            throw new InvalidArgumentException('The callflow insertion path is invalid.');
        }

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];

        if ($path === []) {
            if (array_key_exists($branch, $children)) {
                throw new InvalidArgumentException('The callflow insertion position is still occupied.');
            }

            $children[$branch] = $inserted;
            $node['children'] = $children;

            return;
        }

        $child = $children[$branch] ?? null;

        if (! is_array($child)) {
            throw new InvalidArgumentException('The callflow insertion parent no longer exists.');
        }

        $this->insertAt($child, $path, $inserted);
        $children[$branch] = $child;
        $node['children'] = $children;
    }

    /** @param list<string> $path */
    private function pathStartsWith(array $path, array $prefix): bool
    {
        return count($path) >= count($prefix)
            && array_slice($path, 0, count($prefix)) === $prefix;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function withoutPrivateDocumentFields(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if ($key === 'id' || $key === '_id' || $key === '_rev' || $key === 'account_id'
                || $key === 'created' || $key === 'modified' || str_starts_with($key, 'pvt_')) {
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
