<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Recordings\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;

final readonly class RecordingSnapshot extends EntitySnapshot
{
    public ?string $callId;

    public ?string $cdrId;

    public ?string $interactionId;

    public ?string $ownerId;

    public ?string $direction;

    public ?string $callerIdName;

    public ?string $callerIdNumber;

    public ?string $calleeIdName;

    public ?string $calleeIdNumber;

    public ?string $from;

    public ?string $to;

    public ?string $request;

    public ?int $start;

    public int $durationSeconds;

    public int $durationMilliseconds;

    public ?string $name;

    public ?string $description;

    public ?string $contentType;

    public ?int $contentLength;

    public ?string $mediaSource;

    public ?string $mediaType;

    public ?string $sourceType;

    public ?string $origin;

    /** @var list<string> */
    public array $contentTypes;

    public bool $hasAudio;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->callId = $this->nullableString($data['call_id'] ?? null);
        $this->cdrId = $this->nullableString($data['cdr_id'] ?? null);
        $this->interactionId = $this->nullableString($data['interaction_id'] ?? null);
        $this->ownerId = $this->nullableString($data['owner_id'] ?? null);
        $this->direction = $this->nullableString($data['direction'] ?? null);
        $this->callerIdName = $this->nullableString($data['caller_id_name'] ?? null);
        $this->callerIdNumber = $this->nullableString($data['caller_id_number'] ?? null);
        $this->calleeIdName = $this->nullableString($data['callee_id_name'] ?? null);
        $this->calleeIdNumber = $this->nullableString($data['callee_id_number'] ?? null);
        $this->from = $this->nullableString($data['from'] ?? null);
        $this->to = $this->nullableString($data['to'] ?? null);
        $this->request = $this->nullableString($data['request'] ?? null);
        $this->start = $this->nonNegativeInteger($data['start'] ?? $data['created'] ?? null);
        $this->durationSeconds = $this->nonNegativeInteger($data['duration'] ?? null) ?? 0;
        $this->durationMilliseconds = $this->nonNegativeInteger($data['duration_ms'] ?? null) ?? ($this->durationSeconds * 1000);
        $this->name = $this->nullableString($data['name'] ?? null);
        $this->description = $this->nullableString($data['description'] ?? null);
        $this->contentType = $this->nullableString($data['content_type'] ?? null);
        $this->mediaSource = $this->nullableString($data['media_source'] ?? null);
        $this->mediaType = $this->nullableString($data['media_type'] ?? null);
        $this->sourceType = $this->nullableString($data['source_type'] ?? null);
        $this->origin = $this->nullableString($data['origin'] ?? null);
        $readOnly = is_array($data['_read_only'] ?? null) ? $data['_read_only'] : [];
        $this->contentTypes = $this->stringList($readOnly['content_types'] ?? []);
        $attachments = is_array($data['_attachments'] ?? null) ? $data['_attachments'] : [];
        $firstAttachment = $attachments === [] ? [] : reset($attachments);
        $this->contentLength = is_array($firstAttachment) ? $this->nonNegativeInteger($firstAttachment['length'] ?? null) : null;
        $this->hasAudio = $this->contentTypes !== [] || $attachments !== [] || ($this->contentType !== null && str_starts_with($this->contentType, 'audio/'));
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }
}
