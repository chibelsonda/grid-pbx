<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Faxes\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;

final readonly class FaxMessageSnapshot extends EntitySnapshot
{
    public string $folder;

    public ?string $status;

    public ?string $faxBoxId;

    public ?string $ownerId;

    public ?string $fromName;

    public ?string $fromNumber;

    public ?string $toName;

    public ?string $toNumber;

    public ?string $subject;

    public int $attempts;

    public int $retries;

    public ?bool $successful;

    public ?string $errorMessage;

    public int $pages;

    public int $faxSpeed;

    public int $elapsedSeconds;

    public ?int $createdGregorian;

    public bool $hasDocument;

    public ?string $documentContentType;

    public ?int $documentSize;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, string $folder)
    {
        parent::__construct($data);
        $readOnly = is_array($data['_read_only'] ?? null) ? $data['_read_only'] : [];
        $result = is_array($data['tx_result'] ?? null) ? $data['tx_result'] : (is_array($data['rx_result'] ?? null) ? $data['rx_result'] : []);
        $document = is_array($data['document'] ?? null) ? $data['document'] : [];
        $attachment = $this->firstAttachment($data['_attachments'] ?? null);
        $this->folder = in_array($data['folder'] ?? null, ['inbox', 'outbox'], true) ? $data['folder'] : $folder;
        $this->status = $this->nullableString($data['status'] ?? ($readOnly['status'] ?? null));
        $this->faxBoxId = $this->nullableString($data['faxbox_id'] ?? null);
        $this->ownerId = $this->nullableString($data['owner_id'] ?? null);
        $this->fromName = $this->nullableString($data['from_name'] ?? null);
        $this->fromNumber = $this->nullableString($data['from_number'] ?? ($data['from'] ?? null));
        $this->toName = $this->nullableString($data['to_name'] ?? null);
        $this->toNumber = $this->nullableString($data['to_number'] ?? ($data['to'] ?? null));
        $this->subject = $this->nullableString($data['subject'] ?? null);
        $this->attempts = max(0, (int) ($data['attempts'] ?? 0));
        $this->retries = max(0, min(4, (int) ($data['retries'] ?? 1)));
        $this->successful = array_key_exists('success', $result) ? (bool) $result['success'] : null;
        $this->errorMessage = $this->nullableString($result['error_message'] ?? null);
        $this->pages = max(0, (int) ($result['pages_sent'] ?? ($result['pages_received'] ?? ($data['pages'] ?? 0))));
        $this->faxSpeed = max(0, (int) ($result['fax_speed'] ?? 0));
        $this->elapsedSeconds = max(0, (int) ($result['time_elapsed'] ?? 0));
        $created = $data['created'] ?? ($readOnly['created'] ?? null);
        $this->createdGregorian = is_numeric($created) ? (int) $created : null;
        $this->documentContentType = $this->nullableString($document['content_type'] ?? ($attachment['content_type'] ?? null));
        $size = $attachment['length'] ?? ($data['size'] ?? null);
        $this->documentSize = is_numeric($size) ? max(0, (int) $size) : null;
        $this->hasDocument = $attachment !== [] || $this->nullableString($document['url'] ?? null) !== null;
    }

    /** @return array<string, mixed> */
    private function firstAttachment(mixed $attachments): array
    {
        if (! is_array($attachments) || $attachments === []) {
            return [];
        }
        $first = reset($attachments);

        return is_array($first) ? $first : [];
    }
}
