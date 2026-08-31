<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use GridPbx\Switch\Domains\Callflows\Support\CallflowBranchPolicy;
use InvalidArgumentException;

/**
 * Removes one public callflow subtree while preserving the rest of the document verbatim.
 */
final readonly class CallflowTreeNodeDeleteData
{
    /**
     * @param  array<string, mixed>  $current
     * @param  list<string>  $nodePath
     */
    public function __construct(
        private array $current,
        public array $nodePath,
    ) {
        if (! is_array($this->current['flow'] ?? null)) {
            throw new InvalidArgumentException('Switch callflow must contain a root flow node before its tree can be edited.');
        }

        if ($this->nodePath === []) {
            throw new InvalidArgumentException('The Switch callflow root node cannot be removed.');
        }

        foreach ($this->nodePath as $segment) {
            if (! is_string($segment) || ! CallflowBranchPolicy::isPublicKey($segment)) {
                throw new InvalidArgumentException('The callflow path contains a preserved or unsupported branch.');
            }
        }

        /** @var array<string, mixed> $flow */
        $flow = $this->current['flow'];
        $this->nodeAt($flow, $this->nodePath);
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = $this->withoutPrivateDocumentFields($this->current);
        /** @var array<string, mixed> $flow */
        $flow = $data['flow'];
        $this->removeAt($flow, $this->nodePath);
        $data['flow'] = $this->normalizeNodeForJson($flow);

        return $data;
    }

    /** @param array<string, mixed> $node @param list<string> $path @return array<string, mixed> */
    private function nodeAt(array $node, array $path): array
    {
        foreach ($path as $segment) {
            if (! CallflowBranchPolicy::supports($node, $segment)) {
                throw new InvalidArgumentException('The selected callflow path contains a preserved branch.');
            }

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $child = $children[$segment] ?? null;

            if (! is_array($child)) {
                throw new InvalidArgumentException('The selected callflow path no longer exists.');
            }

            $node = $child;
        }

        return $node;
    }

    /** @param array<string, mixed> $node @param list<string> $path */
    private function removeAt(array &$node, array $path): void
    {
        $branch = array_shift($path);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $child = is_string($branch) ? ($children[$branch] ?? null) : null;

        if (! is_array($child) || ! is_string($branch)) {
            throw new InvalidArgumentException('The selected callflow path no longer exists.');
        }

        if ($path === []) {
            unset($children[$branch]);
            $node['children'] = $children;

            return;
        }

        $this->removeAt($child, $path);
        $children[$branch] = $child;
        $node['children'] = $children;
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
