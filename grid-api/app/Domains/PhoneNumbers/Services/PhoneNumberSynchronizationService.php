<?php

namespace App\Domains\PhoneNumbers\Services;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\PhoneNumbers\Contracts\SwitchPhoneNumberGateway;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Illuminate\Support\Facades\DB;

class PhoneNumberSynchronizationService
{
    public function __construct(
        private readonly SwitchPhoneNumberGateway $gateway,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
    ) {}

    public function handle(SyncRun $run): void
    {
        $run->update([
            'status' => SyncRunStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);

        $account = $run->switchAccount()->firstOrFail();
        $records = [];

        foreach ($this->gateway->all($account) as $snapshot) {
            $number = $this->stringValue($snapshot['id'] ?? null);

            if ($number !== null) {
                $records[$number] = $snapshot;
            }
        }

        DB::transaction(function () use ($account, $records, $run): void {
            $syncedAt = now();
            $callflowIdsByNumber = [];

            foreach (SwitchCallflow::query()
                ->where('switch_account_id', $account->getKey())
                ->get(['callflow_id', 'numbers']) as $callflow) {
                foreach ($callflow->numbers ?? [] as $number) {
                    if (is_string($number) && $number !== '') {
                        $callflowIdsByNumber[$number] = $callflow->getKey();
                    }
                }
            }

            foreach ($records as $number => $snapshot) {
                $readOnly = is_array($snapshot['_read_only'] ?? null) ? $snapshot['_read_only'] : [];
                $cnam = is_array($snapshot['cnam'] ?? null) ? $snapshot['cnam'] : [];
                $e911 = is_array($snapshot['e911'] ?? null) ? $snapshot['e911'] : [];
                $features = $this->stringList($snapshot['features'] ?? ($readOnly['features'] ?? null));
                $projection = SwitchPhoneNumber::withTrashed()->firstOrNew([
                    'switch_account_id' => $account->getKey(),
                    'number' => $number,
                ]);
                $projection->fill([
                    'assigned_callflow_id' => $callflowIdsByNumber[$number] ?? null,
                    'state' => $this->stringValue($snapshot['state'] ?? ($readOnly['state'] ?? null)),
                    'used_by' => $this->stringValue($snapshot['used_by'] ?? ($readOnly['used_by'] ?? null)),
                    'assigned_to_switch_account_id' => $this->stringValue($snapshot['assigned_to'] ?? ($readOnly['assigned_to'] ?? null)),
                    'carrier_name' => $this->stringValue($snapshot['carrier_name'] ?? null),
                    'features' => $features,
                    'cnam_display_name' => $this->stringValue($cnam['display_name'] ?? null),
                    'cnam_inbound_lookup' => (bool) ($cnam['inbound_lookup'] ?? false),
                    'e911_status' => $this->stringValue($e911['status'] ?? null),
                    'source_created_timestamp' => $this->nonNegativeInteger($readOnly['created'] ?? null),
                    'source_updated_timestamp' => $this->nonNegativeInteger($readOnly['modified'] ?? ($readOnly['updated'] ?? null)),
                    'last_synced_at' => $syncedAt,
                    'sync_status' => ProjectionStatus::Healthy,
                    'projection_version' => 1,
                    'switch_json' => $this->redactSensitiveData->handle($snapshot),
                ]);
                $projection->deleted_at = null;
                $projection->save();
            }

            $numbers = array_keys($records);
            $missing = SwitchPhoneNumber::query()
                ->where('switch_account_id', $account->getKey())
                ->when($numbers !== [], fn ($query) => $query->whereNotIn('number', $numbers))
                ->get();
            SwitchPhoneNumber::destroy($missing->modelKeys());

            $run->update([
                'status' => SyncRunStatus::Succeeded,
                'processed_count' => count($records),
                'upserted_count' => count($records),
                'deleted_count' => $missing->count(),
                'finished_at' => now(),
            ]);

            SyncCheckpoint::query()->updateOrCreate([
                'switch_account_id' => $account->getKey(),
                'resource_type' => 'phone_numbers',
            ], [
                'last_sync_run_id' => $run->getKey(),
                'cursor' => null,
                'status' => ProjectionStatus::Healthy,
                'last_successful_at' => now(),
                'error_message' => null,
            ]);
        });
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? $value : [],
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }
}
