<?php

namespace App\Domains\CallDetailRecords\Gateways;

use App\Domains\CallDetailRecords\Contracts\SwitchCallDetailRecordGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use DateTimeInterface;
use Generator;
use GridPbx\Switch\Resources\CallDetailRecordResourceClient;

class CrossbarSwitchCallDetailRecordGateway implements SwitchCallDetailRecordGateway
{
    public function __construct(private readonly CallDetailRecordResourceClient $records) {}

    public function all(SwitchAccount $account, DateTimeInterface $from, DateTimeInterface $to): Generator
    {
        foreach ($this->records->all(
            $account->switch_account_id,
            $from->getTimestamp(),
            $to->getTimestamp(),
        ) as $snapshot) {
            yield [
                'switch_resource_id' => $snapshot->id,
                'call_id' => $snapshot->callId,
                'interaction_id' => $snapshot->interactionId,
                'direction' => $snapshot->direction,
                'caller_id_name' => $snapshot->callerIdName,
                'caller_id_number' => $snapshot->callerIdNumber,
                'callee_id_name' => $snapshot->calleeIdName,
                'callee_id_number' => $snapshot->calleeIdNumber,
                'from_uri' => $snapshot->from,
                'to_uri' => $snapshot->to,
                'request_uri' => $snapshot->request,
                'started_at_unix' => $snapshot->startedAtUnix,
                'duration_seconds' => $snapshot->durationSeconds,
                'billing_seconds' => $snapshot->billingSeconds,
                'hangup_cause' => $snapshot->hangupCause,
                'disposition' => $snapshot->disposition,
                'owner_switch_resource_id' => $this->stringValue($snapshot->toArray()['owner_id'] ?? null),
                'recording_available' => $this->hasRecording($snapshot->toArray()),
                'data' => $snapshot->toArray(),
            ];
        }
    }

    /** @param array<string, mixed> $data */
    private function hasRecording(array $data): bool
    {
        return $this->stringValue($data['recording_url'] ?? null) !== null
            || (is_array($data['media_recordings'] ?? null) && $data['media_recordings'] !== []);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
