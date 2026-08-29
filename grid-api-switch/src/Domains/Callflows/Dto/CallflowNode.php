<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class CallflowNode
{
    /** @param array<string, self> $children */
    private function __construct(
        public string $module,
        public array $children,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $module = $data['module'] ?? null;

        if (! is_string($module) || $module === '') {
            throw new InvalidSwitchPayloadException('Switch callflow node must contain a non-empty module.');
        }

        $rawChildren = $data['children'] ?? [];

        if (! is_array($rawChildren)) {
            throw new InvalidSwitchPayloadException('Switch callflow node children must be an object.');
        }

        $children = [];

        foreach ($rawChildren as $branch => $child) {
            if (! is_string($branch) || ! is_array($child)) {
                throw new InvalidSwitchPayloadException('Switch callflow child branches must contain callflow nodes.');
            }

            $children[$branch] = self::fromArray($child);
        }

        return new self($module, $children);
    }

    /** @return list<string> */
    public function modules(): array
    {
        $modules = [$this->module];

        foreach ($this->children as $child) {
            array_push($modules, ...$child->modules());
        }

        return $modules;
    }

    public function nodeCount(): int
    {
        return 1 + array_sum(array_map(
            static fn (self $child): int => $child->nodeCount(),
            $this->children,
        ));
    }

    public function maxDepth(): int
    {
        if ($this->children === []) {
            return 1;
        }

        return 1 + max(array_map(
            static fn (self $child): int => $child->maxDepth(),
            $this->children,
        ));
    }

    /** @return array{module: string, children: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'children' => array_map(
                static fn (self $child): array => $child->toArray(),
                $this->children,
            ),
        ];
    }
}
