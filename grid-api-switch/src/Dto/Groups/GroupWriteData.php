<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Groups;

use InvalidArgumentException;

final readonly class GroupWriteData
{
    /** @param list<GroupEndpointWriteData> $endpoints */
    public function __construct(
        public string $name,
        public array $endpoints,
        public ?string $musicOnHoldMediaId = null,
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch group name is required.');
        }

        foreach ($this->endpoints as $endpoint) {
            if (! $endpoint instanceof GroupEndpointWriteData) {
                throw new InvalidArgumentException('Switch group endpoints must be typed endpoint data.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $endpoints = [];

        foreach ($this->endpoints as $endpoint) {
            $endpoints[$endpoint->resourceId] = [
                'type' => $endpoint->type,
                'weight' => $endpoint->weight,
            ];
        }

        return [
            'name' => $this->name,
            'endpoints' => $endpoints,
            'music_on_hold' => $this->musicOnHoldMediaId === null
                ? []
                : ['media_id' => $this->musicOnHoldMediaId],
        ];
    }
}
