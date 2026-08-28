<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\LineKeys;

use InvalidArgumentException;

final readonly class LineKeyWriteData
{
    private const TYPES = ['line', 'presence', 'personal_parking', 'speed_dial', 'parking'];

    public function __construct(
        public string $category,
        public int $position,
        public string $type,
        public string|int|null $value = null,
        public ?string $label = null,
    ) {
        if (! in_array($this->category, ['combo', 'feature'], true)) {
            throw new InvalidArgumentException('Switch line-key category must be combo or feature.');
        }

        if ($this->position < 0) {
            throw new InvalidArgumentException('Switch line-key position must be zero or greater.');
        }

        if (! in_array($this->type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported Switch line-key type.');
        }

        if ($this->label !== null && $this->value === null) {
            throw new InvalidArgumentException('A labeled Switch line key requires a value.');
        }

        if ($this->type === 'parking' && $this->value !== null) {
            $parkingPosition = filter_var($this->value, FILTER_VALIDATE_INT);

            if ($parkingPosition === false || $parkingPosition < 1 || $parkingPosition > 10) {
                throw new InvalidArgumentException('A parking line-key value must be between 1 and 10.');
            }
        } elseif (is_int($this->value)) {
            throw new InvalidArgumentException('Non-parking Switch line-key values must be strings.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = ['type' => $this->type];

        if ($this->value !== null) {
            $value = $this->type === 'parking' ? (int) $this->value : $this->value;
            $data['value'] = $this->label === null
                ? $value
                : ['label' => $this->label, 'value' => $value];
        }

        return $data;
    }
}
