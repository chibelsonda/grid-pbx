<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Menus\Dto;

use InvalidArgumentException;

final readonly class MenuWriteData
{
    /** @param list<string> $flags */
    public function __construct(
        public string $name,
        public int $timeout = 10000,
        public int $interdigitTimeout = 2000,
        public int $maxExtensionLength = 4,
        public int $retries = 3,
        public bool $hunt = true,
        public bool $allowRecordFromOffnet = false,
        public bool $suppressMedia = false,
        public ?string $recordPin = null,
        public ?string $huntAllow = null,
        public ?string $huntDeny = null,
        public ?string $greetingMediaId = null,
        public string|bool|null $invalidMedia = true,
        public string|bool|null $transferMedia = true,
        public string|bool|null $exitMedia = true,
        public array $flags = [],
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch menu name is required.');
        }

        if ($this->timeout < 1 || $this->timeout > 60000 || $this->interdigitTimeout < 1 || $this->interdigitTimeout > 10000) {
            throw new InvalidArgumentException('Switch menu timeout settings are invalid.');
        }

        if ($this->maxExtensionLength < 1 || $this->maxExtensionLength > 6 || $this->retries < 1 || $this->retries > 10) {
            throw new InvalidArgumentException('Switch menu digit settings are invalid.');
        }

        if ($this->recordPin !== null && (strlen($this->recordPin) < 3 || strlen($this->recordPin) > 6 || ! ctype_digit($this->recordPin))) {
            throw new InvalidArgumentException('Switch menu record PIN must contain 3 to 6 digits.');
        }

        foreach ($this->flags as $flag) {
            if (! is_string($flag) || $flag === '') {
                throw new InvalidArgumentException('Switch menu flags must contain non-empty strings.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = [
            'name' => $this->name,
            'timeout' => $this->timeout,
            'interdigit_timeout' => $this->interdigitTimeout,
            'max_extension_length' => $this->maxExtensionLength,
            'retries' => $this->retries,
            'hunt' => $this->hunt,
            'allow_record_from_offnet' => $this->allowRecordFromOffnet,
            'suppress_media' => $this->suppressMedia,
            'flags' => array_values($this->flags),
            'media' => array_filter([
                'greeting' => $this->greetingMediaId,
                'invalid_media' => $this->invalidMedia,
                'transfer_media' => $this->transferMedia,
                'exit_media' => $this->exitMedia,
            ], static fn (mixed $value): bool => $value !== null),
        ];

        foreach (['record_pin' => $this->recordPin, 'hunt_allow' => $this->huntAllow, 'hunt_deny' => $this->huntDeny] as $key => $value) {
            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
