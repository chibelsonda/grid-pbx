<?php

namespace App\Domains\Faxes\Services;

use App\Domains\Faxes\Models\SwitchFax;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Carbon\CarbonImmutable;
use UnexpectedValueException;

class FaxProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactor) {}
    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchFax
    {
        $resourceId = $this->string($snapshot['switch_resource_id'] ?? null); $folder = $snapshot['folder'] ?? null;
        if ($resourceId === null || ! in_array($folder, ['inbox', 'outbox'], true)) throw new UnexpectedValueException('Switch fax response is missing required metadata.');
        $boxReference = $this->string($snapshot['fax_box_switch_resource_id'] ?? null); $ownerReference = $this->string($snapshot['owner_switch_resource_id'] ?? null);
        $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
        $redacted = $this->redactor->handle($data);
        if (is_array($redacted['document'] ?? null)) foreach (['content', 'url', 'host', 'referer'] as $key) if (array_key_exists($key, $redacted['document'])) $redacted['document'][$key] = '[REDACTED]';
        $fax = SwitchFax::withTrashed()->firstOrNew(['switch_account_id' => $account->getKey(), 'folder' => $folder, 'switch_resource_id' => $resourceId]);
        $fax->fill([
            'fax_box_switch_resource_id' => $boxReference, 'switch_fax_box_id' => $boxReference === null ? null : $account->faxBoxes()->where('switch_resource_id', $boxReference)->value('fax_box_id'),
            'owner_switch_resource_id' => $ownerReference, 'switch_extension_id' => $ownerReference === null ? null : $account->extensions()->where('switch_resource_id', $ownerReference)->value('extension_id'),
            'status' => $this->string($snapshot['status'] ?? null), 'from_name' => $this->string($snapshot['from_name'] ?? null), 'from_number' => $this->string($snapshot['from_number'] ?? null),
            'to_name' => $this->string($snapshot['to_name'] ?? null), 'to_number' => $this->string($snapshot['to_number'] ?? null), 'subject' => $this->string($snapshot['subject'] ?? null),
            'attempts' => max(0, (int) ($snapshot['attempts'] ?? 0)), 'retries' => max(0, min(4, (int) ($snapshot['retries'] ?? 1))),
            'successful' => is_bool($snapshot['successful'] ?? null) ? $snapshot['successful'] : null, 'error_message' => $this->string($snapshot['error_message'] ?? null),
            'pages' => max(0, (int) ($snapshot['pages'] ?? 0)), 'fax_speed' => max(0, (int) ($snapshot['fax_speed'] ?? 0)), 'elapsed_seconds' => max(0, (int) ($snapshot['elapsed_seconds'] ?? 0)),
            'switch_created_at' => is_int($snapshot['switch_created_at_unix'] ?? null) ? CarbonImmutable::createFromTimestampUTC($snapshot['switch_created_at_unix']) : null,
            'has_document' => (bool) ($snapshot['has_document'] ?? false), 'document_content_type' => $this->string($snapshot['document_content_type'] ?? null),
            'document_size' => is_int($snapshot['document_size'] ?? null) ? max(0, $snapshot['document_size']) : null,
            'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => $fax->exists ? $fax->projection_version + 1 : 1, 'switch_json' => $redacted,
        ]);
        $fax->deleted_at = null; $fax->save(); return $fax->load(['faxBox', 'owner']);
    }
    private function string(mixed $value): ?string { return is_string($value) && $value !== '' ? $value : null; }
}
