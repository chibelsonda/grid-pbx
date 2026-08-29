<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Menus\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class MenuSnapshot extends EntitySnapshot
{
    public string $name;
    public int $timeout;
    public int $interdigitTimeout;
    public int $maxExtensionLength;
    public int $retries;
    public bool $hunt;
    public bool $allowRecordFromOffnet;
    public bool $suppressMedia;
    public ?string $recordPin;
    public ?string $huntAllow;
    public ?string $huntDeny;
    public ?string $greetingMediaId;
    public string|bool|null $invalidMedia;
    public string|bool|null $transferMedia;
    public string|bool|null $exitMedia;
    /** @var list<string> */
    public array $flags;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $name = $data['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw new InvalidSwitchPayloadException('Switch menu response is missing its name.');
        }

        $media = is_array($data['media'] ?? null) ? $data['media'] : [];
        $this->name = $name;
        $this->timeout = max(1, (int) ($data['timeout'] ?? 10000));
        $this->interdigitTimeout = max(1, (int) ($data['interdigit_timeout'] ?? 2000));
        $this->maxExtensionLength = max(1, min(6, (int) ($data['max_extension_length'] ?? 4)));
        $this->retries = max(1, min(10, (int) ($data['retries'] ?? 3)));
        $this->hunt = (bool) ($data['hunt'] ?? true);
        $this->allowRecordFromOffnet = (bool) ($data['allow_record_from_offnet'] ?? false);
        $this->suppressMedia = (bool) ($data['suppress_media'] ?? false);
        $this->recordPin = $this->nullableString($data['record_pin'] ?? null);
        $this->huntAllow = $this->nullableString($data['hunt_allow'] ?? null);
        $this->huntDeny = $this->nullableString($data['hunt_deny'] ?? null);
        $this->greetingMediaId = $this->nullableString($media['greeting'] ?? null);
        $this->invalidMedia = $this->mediaValue($media['invalid_media'] ?? null);
        $this->transferMedia = $this->mediaValue($media['transfer_media'] ?? null);
        $this->exitMedia = $this->mediaValue($media['exit_media'] ?? null);
        $this->flags = $this->stringList($data['flags'] ?? null);
    }

    private function mediaValue(mixed $value): string|bool|null
    {
        return is_string($value) || is_bool($value) ? $value : null;
    }
}
