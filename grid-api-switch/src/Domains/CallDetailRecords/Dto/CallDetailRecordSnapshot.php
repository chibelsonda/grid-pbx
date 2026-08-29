<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\CallDetailRecords\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class CallDetailRecordSnapshot
{
    public string $id;

    public string $callId;

    public ?string $interactionId;

    public ?string $direction;

    public ?string $callerIdName;

    public ?string $callerIdNumber;

    public ?string $calleeIdName;

    public ?string $calleeIdNumber;

    public ?string $from;

    public ?string $to;

    public ?string $request;

    public int $startedAtUnix;

    public int $durationSeconds;

    public int $billingSeconds;

    public ?string $hangupCause;

    public ?string $disposition;

    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
        $this->id = $this->requiredString($data['id'] ?? null, 'id');
        $this->callId = $this->requiredString($data['call_id'] ?? null, 'call_id');
        $this->interactionId = $this->nullableString($data['interaction_id'] ?? null);
        $this->direction = $this->direction($data['direction'] ?? ($data['call_direction'] ?? null));
        $this->callerIdName = $this->nullableString($data['caller_id_name'] ?? null);
        $this->callerIdNumber = $this->nullableString($data['caller_id_number'] ?? null);
        $this->calleeIdName = $this->nullableString($data['callee_id_name'] ?? null);
        $this->calleeIdNumber = $this->nullableString($data['callee_id_number'] ?? null);
        $this->from = $this->nullableString($data['from'] ?? null);
        $this->to = $this->nullableString($data['to'] ?? null);
        $this->request = $this->nullableString($data['request'] ?? null);
        $this->startedAtUnix = $this->requiredNonNegativeInteger(
            $data['unix_timestamp'] ?? null,
            'unix_timestamp',
        );
        $this->durationSeconds = $this->nonNegativeInteger($data['duration_seconds'] ?? null) ?? 0;
        $this->billingSeconds = $this->nonNegativeInteger($data['billing_seconds'] ?? null) ?? 0;
        $this->hangupCause = $this->nullableString($data['hangup_cause'] ?? null);
        $this->disposition = $this->nullableString($data['disposition'] ?? null);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    private function requiredString(mixed $value, string $field): string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            throw new InvalidSwitchPayloadException("Switch CDR must contain a non-empty {$field}.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function direction(mixed $value): ?string
    {
        $direction = $this->nullableString($value);

        if ($direction !== null && ! in_array($direction, ['inbound', 'outbound'], true)) {
            throw new InvalidSwitchPayloadException('Switch CDR direction must be inbound or outbound.');
        }

        return $direction;
    }

    private function requiredNonNegativeInteger(mixed $value, string $field): int
    {
        $integer = $this->nonNegativeInteger($value);

        if ($integer === null) {
            throw new InvalidSwitchPayloadException("Switch CDR {$field} must be a non-negative integer.");
        }

        return $integer;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }
}
