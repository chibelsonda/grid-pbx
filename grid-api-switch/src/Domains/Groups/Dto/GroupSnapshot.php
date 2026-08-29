<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Groups\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class GroupSnapshot extends EntitySnapshot
{
    public string $name;

    public ?string $musicOnHoldMediaId;

    /** @var list<GroupEndpointSnapshot> */
    public array $endpoints;

    /** @var list<string> */
    public array $flags;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $name = $data['name'] ?? null;
        $endpoints = $data['endpoints'] ?? [];

        if (! is_string($name) || trim($name) === '' || ! is_array($endpoints)) {
            throw new InvalidSwitchPayloadException('Switch group response is invalid.');
        }

        $this->name = $name;
        $musicOnHold = is_array($data['music_on_hold'] ?? null) ? $data['music_on_hold'] : [];
        $this->musicOnHoldMediaId = $this->nullableString($musicOnHold['media_id'] ?? null);
        $this->flags = $this->stringList($data['flags'] ?? null);
        $this->endpoints = array_map(
            static fn (mixed $endpoint, string|int $resourceId): GroupEndpointSnapshot => is_array($endpoint)
                ? GroupEndpointSnapshot::fromArray((string) $resourceId, $endpoint)
                : throw new InvalidSwitchPayloadException('Switch group endpoints must contain objects.'),
            $endpoints,
            array_keys($endpoints),
        );
    }
}
