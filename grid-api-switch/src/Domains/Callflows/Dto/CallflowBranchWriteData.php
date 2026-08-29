<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Callflows\Dto;

use InvalidArgumentException;

final readonly class CallflowBranchWriteData
{
    public function __construct(
        public string $key,
        public ?string $module,
        public ?string $resourceId,
    ) {
        if (trim($this->key) === '' || $this->key === '_' || mb_strlen($this->key) > 128) {
            throw new InvalidArgumentException('Invalid Switch callflow branch key.');
        }

        if (($this->module === null) !== ($this->resourceId === null)) {
            throw new InvalidArgumentException('Switch callflow branch module and identifier must be provided together.');
        }

        if ($this->resourceId !== null && trim($this->resourceId) === '') {
            throw new InvalidArgumentException('Switch callflow branch identifier is required.');
        }
    }

    public function clearsBranch(): bool
    {
        return $this->module === null;
    }
}
