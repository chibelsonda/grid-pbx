<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\PhoneNumbers\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;

final readonly class PhoneNumberSnapshot extends EntitySnapshot
{
    public string $number;

    public ?string $state;

    public ?string $usedBy;

    public ?string $assignedTo;

    public ?string $carrierName;

    /** @var list<string> */
    public array $features;

    public ?string $cnamDisplayName;

    public bool $cnamInboundLookup;

    public ?string $e911Status;

    public ?int $sourceCreatedTimestamp;

    public ?int $sourceUpdatedTimestamp;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->number = $this->id;
        $this->state = $this->nullableString($data['state'] ?? null)
            ?? $this->nestedString('_read_only', 'state');
        $this->usedBy = $this->nullableString($data['used_by'] ?? null)
            ?? $this->nestedString('_read_only', 'used_by');
        $this->assignedTo = $this->nullableString($data['assigned_to'] ?? null)
            ?? $this->nestedString('_read_only', 'assigned_to');
        $this->carrierName = $this->nullableString($data['carrier_name'] ?? null);
        $this->features = $this->stringList($data['features'] ?? ($data['_read_only']['features'] ?? null));
        $this->cnamDisplayName = $this->nestedString('cnam', 'display_name');
        $this->cnamInboundLookup = (bool) ($data['cnam']['inbound_lookup'] ?? false);
        $this->e911Status = $this->nestedString('e911', 'status');
        $this->sourceCreatedTimestamp = $this->integer($data['_read_only']['created'] ?? null);
        $this->sourceUpdatedTimestamp = $this->integer(
            $data['_read_only']['modified'] ?? ($data['_read_only']['updated'] ?? null),
        );
    }

    private function integer(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }
}
