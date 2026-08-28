<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Devices;

use InvalidArgumentException;

final readonly class DeviceWriteData
{
    public function __construct(
        public string $name,
        public string $deviceType,
        public bool $enabled,
        public ?string $ownerId = null,
        public ?string $make = null,
        public ?string $model = null,
        public ?string $macAddress = null,
        private ?string $sipUsername = null,
        private ?string $sipPassword = null,
        public ?string $family = null,
        public ?DeviceCallForwardData $callForward = null,
        public ?DeviceSipData $sip = null,
        public ?DeviceMediaData $media = null,
        public ?DeviceCallerIdData $callerId = null,
        public ?DeviceAdvancedData $advanced = null,
        public ?DeviceCallRecordingData $callRecording = null,
        public ?DeviceMusicOnHoldData $musicOnHold = null,
        public ?DeviceOutboundFlagsData $outboundFlags = null,
        public ?DeviceDialPlanData $dialPlan = null,
        public ?DeviceMetaflowsData $metaflows = null,
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch device name is required.');
        }

        if (trim($this->deviceType) === '') {
            throw new InvalidArgumentException('Switch device type is required.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = [
            'name' => $this->name,
            'device_type' => $this->deviceType,
            'enabled' => $this->enabled,
        ];

        if ($this->ownerId !== null) {
            $data['owner_id'] = $this->ownerId;
        }

        if ($this->macAddress !== null) {
            $data['mac_address'] = $this->macAddress;
        }

        $provision = array_filter([
            'endpoint_brand' => $this->make,
            'endpoint_family' => $this->family,
            'endpoint_model' => $this->model,
        ], static fn (?string $value): bool => $value !== null);

        if ($provision !== []) {
            $data['provision'] = $provision;
        }

        $legacySip = array_filter([
            'username' => $this->sipUsername,
            'password' => $this->sipPassword,
        ], static fn (?string $value): bool => $value !== null);

        $sip = $this->sip?->toSwitchData() ?? [];
        $sip = array_replace($sip, $legacySip);

        if ($sip !== []) {
            $data['sip'] = $sip;
        }

        if ($this->callForward !== null) {
            $data['call_forward'] = $this->callForward->toSwitchData();
        }

        if ($this->media !== null) {
            $data['media'] = $this->media->toSwitchData();
        }

        if ($this->callerId !== null) {
            $callerId = $this->callerId->toSwitchData();

            if ($callerId !== []) {
                $data['caller_id'] = $callerId;
            }
        }

        if ($this->callRecording !== null) {
            $data['call_recording'] = $this->callRecording->toSwitchData();
        }

        if ($this->musicOnHold !== null) {
            $data['music_on_hold'] = $this->musicOnHold->toSwitchData();
        }

        if ($this->outboundFlags !== null) {
            $data['outbound_flags'] = $this->outboundFlags->toSwitchData();
        }

        if ($this->dialPlan !== null) {
            $data['dial_plan'] = $this->dialPlan->toSwitchData();
        }

        if ($this->metaflows !== null) {
            $data['metaflows'] = $this->metaflows->toSwitchData();
        }

        if ($this->advanced !== null) {
            $data = array_replace($data, $this->advanced->toSwitchData());
        }

        return $data;
    }
}
