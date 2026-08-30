<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class AccountHierarchySnapshot
{
    public string $id;

    public ?string $name;

    public ?string $realm;

    /** @var list<string> */
    public array $tree;

    public ?string $parentId;

    public int $descendantsCount;

    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
        $id = $data['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new InvalidSwitchPayloadException('Switch account hierarchy entry must contain a non-empty id.');
        }

        $tree = is_array($data['tree'] ?? null) ? $data['tree'] : [];
        $this->tree = array_values(array_filter(
            $tree,
            static fn (mixed $ancestorId): bool => is_string($ancestorId) && $ancestorId !== '',
        ));
        $this->id = $id;
        $this->name = $this->nullableString($data['name'] ?? null);
        $this->realm = $this->nullableString($data['realm'] ?? null);
        $this->parentId = $this->tree === [] ? null : $this->tree[array_key_last($this->tree)];
        $this->descendantsCount = is_numeric($data['descendants_count'] ?? null)
            ? max(0, (int) $data['descendants_count'])
            : 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
