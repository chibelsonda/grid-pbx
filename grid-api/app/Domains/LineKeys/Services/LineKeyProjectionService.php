<?php

namespace App\Domains\LineKeys\Services;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\LineKeys\Models\SwitchLineKey;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use GridPbx\Switch\Domains\LineKeys\Dto\DeviceProvisioningSnapshot;
use GridPbx\Switch\Domains\LineKeys\Dto\LineKeySnapshot;

class LineKeyProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactor) {}

    /** @param array<string, mixed> $deviceSnapshot */
    public function project(SwitchDevice $device, array $deviceSnapshot): void
    {
        $snapshot = new DeviceProvisioningSnapshot($deviceSnapshot);
        $projected = [];

        foreach ($snapshot->lineKeys as $key) {
            $projected[] = $this->projectKey($device, $key)->getKey();
        }

        $missing = $device->lineKeys()
            ->when($projected !== [], fn ($query) => $query->whereNotIn('line_key_id', $projected))
            ->get();
        SwitchLineKey::destroy($missing->modelKeys());
    }

    private function projectKey(SwitchDevice $device, LineKeySnapshot $snapshot): SwitchLineKey
    {
        $key = SwitchLineKey::withTrashed()->firstOrNew([
            'switch_device_id' => $device->getKey(),
            'category' => $snapshot->category,
            'position' => $snapshot->position,
        ]);
        $key->fill([
            'type' => $snapshot->type,
            'label' => $snapshot->label,
            'value' => $snapshot->value === null ? null : (string) $snapshot->value,
            'switch_json' => $snapshot->data === null ? null : $this->redactor->handle($snapshot->data),
        ]);
        $key->deleted_at = null;
        $key->save();

        return $key;
    }
}
