<?php

namespace App\Domains\Faxes\Gateways;

use App\Domains\Faxes\Contracts\SwitchFaxGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;
use Generator;
use GridPbx\Switch\Http\BinaryResponse;
use GridPbx\Switch\Resources\FaxMessageResourceClient;

class CrossbarSwitchFaxGateway implements SwitchFaxGateway
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;
    public function __construct(private readonly FaxMessageResourceClient $faxes) {}
    public function all(SwitchAccount $account, string $folder, CarbonImmutable $from, CarbonImmutable $to): Generator
    {
        foreach ($this->faxes->all($account->switch_account_id, $folder, $from->timestamp, $to->timestamp) as $fax) {
            yield [
                'switch_resource_id' => $fax->id, 'folder' => $fax->folder, 'status' => $fax->status,
                'fax_box_switch_resource_id' => $fax->faxBoxId, 'owner_switch_resource_id' => $fax->ownerId,
                'from_name' => $fax->fromName, 'from_number' => $fax->fromNumber, 'to_name' => $fax->toName,
                'to_number' => $fax->toNumber, 'subject' => $fax->subject, 'attempts' => $fax->attempts,
                'retries' => $fax->retries, 'successful' => $fax->successful, 'error_message' => $fax->errorMessage,
                'pages' => $fax->pages, 'fax_speed' => $fax->faxSpeed, 'elapsed_seconds' => $fax->elapsedSeconds,
                'switch_created_at_unix' => $fax->createdGregorian === null ? null : $fax->createdGregorian - self::GREGORIAN_UNIX_OFFSET,
                'has_document' => $fax->hasDocument, 'document_content_type' => $fax->documentContentType,
                'document_size' => $fax->documentSize, 'data' => $fax->toArray(),
            ];
        }
    }
    public function document(SwitchAccount $account, string $folder, string $resourceId, ?string $range = null): BinaryResponse { return $this->faxes->document($account->switch_account_id, $folder, $resourceId, $range); }
}
