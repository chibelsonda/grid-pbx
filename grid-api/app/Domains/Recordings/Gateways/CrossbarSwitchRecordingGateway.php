<?php

namespace App\Domains\Recordings\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Recordings\Contracts\SwitchRecordingGateway;
use Carbon\CarbonImmutable;
use Generator;
use GridPbx\Switch\Http\BinaryResponse;
use GridPbx\Switch\Resources\RecordingResourceClient;

class CrossbarSwitchRecordingGateway implements SwitchRecordingGateway
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;
    public function __construct(private readonly RecordingResourceClient $recordings) {}
    public function all(SwitchAccount $account, CarbonImmutable $from, CarbonImmutable $to): Generator
    {
        foreach ($this->recordings->all($account->switch_account_id, $from->timestamp, $to->timestamp) as $recording) {
            yield ['switch_resource_id' => $recording->id, 'owner_switch_resource_id' => $recording->ownerId, 'call_id' => $recording->callId, 'cdr_id' => $recording->cdrId, 'interaction_id' => $recording->interactionId, 'direction' => $recording->direction, 'caller_id_name' => $recording->callerIdName, 'caller_id_number' => $recording->callerIdNumber, 'callee_id_name' => $recording->calleeIdName, 'callee_id_number' => $recording->calleeIdNumber, 'from_uri' => $recording->from, 'to_uri' => $recording->to, 'request_uri' => $recording->request, 'started_at_unix' => $recording->start === null ? null : $recording->start - self::GREGORIAN_UNIX_OFFSET, 'duration_seconds' => $recording->durationSeconds, 'duration_milliseconds' => $recording->durationMilliseconds, 'name' => $recording->name, 'description' => $recording->description, 'content_type' => $recording->contentType ?? ($recording->contentTypes[0] ?? null), 'content_length' => $recording->contentLength, 'media_source' => $recording->mediaSource, 'media_type' => $recording->mediaType, 'source_type' => $recording->sourceType, 'origin' => $recording->origin, 'has_audio' => $recording->hasAudio, 'data' => $recording->toArray()];
        }
    }
    public function audio(SwitchAccount $account, string $resourceId, ?string $range = null): BinaryResponse { return $this->recordings->audio($account->switch_account_id, $resourceId, $range); }
}
