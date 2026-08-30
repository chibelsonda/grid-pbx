<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\CallerIdLists\Dto;

final readonly class CallerIdListDetails
{
    /** @param list<CallerIdListEntrySnapshot> $entries */
    public function __construct(
        public CallerIdListSnapshot $list,
        public array $entries,
    ) {}
}
