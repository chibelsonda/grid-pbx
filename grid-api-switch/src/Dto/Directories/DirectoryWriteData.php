<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Directories;

use InvalidArgumentException;

final readonly class DirectoryWriteData
{
    public function __construct(
        public string $name,
        public bool $confirmMatch = true,
        public int $minDtmf = 3,
        public int $maxDtmf = 0,
        public string $sortBy = 'last_name',
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch directory name is required.');
        }

        if ($this->minDtmf < 1 || $this->maxDtmf < 0) {
            throw new InvalidArgumentException('Switch directory DTMF limits are invalid.');
        }

        if (! in_array($this->sortBy, ['first_name', 'last_name'], true)) {
            throw new InvalidArgumentException('Switch directory sort field is invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return [
            'name' => $this->name,
            'confirm_match' => $this->confirmMatch,
            'min_dtmf' => $this->minDtmf,
            'max_dtmf' => $this->maxDtmf,
            'sort_by' => $this->sortBy,
        ];
    }
}
