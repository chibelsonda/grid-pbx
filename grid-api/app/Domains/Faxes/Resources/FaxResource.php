<?php

namespace App\Domains\Faxes\Resources;

use App\Domains\Faxes\Models\SwitchFax;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchFax */
class FaxResource extends JsonResource
{
    /**
     * @return array<string, array{switch_supported: true, enabled: false, reason: string}>
     */
    public static function operationCapabilities(): array
    {
        return [
            'send' => self::disabledOperation(
                'The installed Switch supports asynchronous outbound Fax jobs, but GridPBX has not approved document conversion, retention, notification, billing, or abuse-control policy.',
            ),
            'forward' => self::disabledOperation(
                'Forwarding copies a retained inbound document into a new outbound job. It remains disabled pending destination confirmation, retention, authorization, audit, and reconciliation policy.',
            ),
            'resubmit' => self::disabledOperation(
                'Resubmission copies an outbox document into a new outbound job. It remains disabled pending duplicate-safe execution, exact-message confirmation, audit, and reconciliation policy.',
            ),
            'delete_message' => self::disabledOperation(
                'Permanent Fax message deletion remains disabled pending retention, legal-hold, exact-message confirmation, authorization, and immutable audit policy.',
            ),
            'delete_document' => self::disabledOperation(
                'Fax document deletion is separate from message deletion and remains disabled pending binary-retention, legal-hold, confirmation, and reconciliation policy.',
            ),
        ];
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'folder' => $this->folder, 'status' => $this->status,
            'fax_box' => $this->whenLoaded('faxBox', fn () => $this->faxBox === null ? null : ['id' => $this->faxBox->id, 'name' => $this->faxBox->name]),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : ['id' => $this->owner->id, 'label' => $this->owner->display_name, 'extension' => $this->owner->extension]),
            'from' => ['name' => $this->from_name, 'number' => $this->from_number], 'to' => ['name' => $this->to_name, 'number' => $this->to_number],
            'subject' => $this->subject, 'attempts' => $this->attempts, 'retries' => $this->retries, 'successful' => $this->successful,
            'error_message' => $this->error_message, 'pages' => $this->pages, 'fax_speed' => $this->fax_speed, 'elapsed_seconds' => $this->elapsed_seconds,
            'created_at' => $this->switch_created_at?->toIso8601String(), 'has_document' => $this->has_document,
            'document_content_type' => $this->document_content_type, 'document_size' => $this->document_size,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value,
        ];
    }

    /** @return array{switch_supported: true, enabled: false, reason: string} */
    private static function disabledOperation(string $reason): array
    {
        return [
            'switch_supported' => true,
            'enabled' => false,
            'reason' => $reason,
        ];
    }
}
