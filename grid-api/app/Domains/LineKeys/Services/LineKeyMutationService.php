<?php

namespace App\Domains\LineKeys\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Devices\Services\ProvisioningModelCapabilitiesService;
use App\Domains\Devices\Services\StarterDevicePolicy;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\LineKeys\Contracts\SwitchLineKeyGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class LineKeyMutationService
{
    public function __construct(
        private readonly SwitchLineKeyGateway $gateway,
        private readonly LineKeyProjectionService $projection,
        private readonly RedactSensitiveSwitchData $redactor,
        private readonly AuditService $audit,
        private readonly ProvisioningModelCapabilitiesService $modelCapabilities,
        private readonly LineKeyReferenceResolver $references,
        private readonly StarterDevicePolicy $devicePolicy,
    ) {}

    /** @param list<array{category: string, position: int, type: string, value: string|int|null, label: string|null}> $keys */
    public function update(SwitchAccount $account, SwitchDevice $device, User $actor, array $keys, ?string $ipAddress): SwitchDevice
    {
        if (! $this->devicePolicy->isProvisionable($device->device_type)) {
            throw new ConflictHttpException('Line keys are not supported for this device type.');
        }

        if (! config('switch.line_key_mutations_enabled', false)) {
            throw new ConflictHttpException('Line-key mutations are disabled by server configuration.');
        }

        if ($device->switch_resource_id === null) {
            throw new ConflictHttpException('The device must be synchronized from Switch before line keys can be applied.');
        }

        if ($device->make === null || $device->model === null || $device->mac_address === null) {
            throw new ConflictHttpException('The device needs an endpoint brand, model, and MAC address before it can be provisioned.');
        }

        $this->modelCapabilities->assertKeysFit($device, $keys);
        $switchKeys = $this->references->useSwitchValues($account, $keys);

        try {
            $snapshot = $this->gateway->update($account, $device->switch_resource_id, $switchKeys);

            return DB::transaction(function () use ($account, $device, $actor, $keys, $ipAddress, $snapshot): SwitchDevice {
                $device->fill([
                    'make' => $this->stringValue($snapshot['make'] ?? Arr::get($snapshot, 'provision.endpoint_brand')),
                    'endpoint_family' => $this->stringValue(Arr::get($snapshot, 'provision.endpoint_family')),
                    'model' => $this->stringValue($snapshot['model'] ?? Arr::get($snapshot, 'provision.endpoint_model')),
                    'switch_json' => $this->redactor->handle($snapshot),
                    'last_synced_at' => now(),
                    'projection_version' => $device->projection_version + 1,
                ]);
                $device->save();
                $this->projection->project($device, $snapshot);
                $this->audit->record($actor, $account, 'line_keys.updated', 'succeeded', $device->switch_resource_id, [
                    'device_id' => $device->id,
                    'key_count' => count($keys),
                ], $ipAddress);

                $updated = $device->load('lineKeys');
                $this->references->usePublicValues($account, $updated->lineKeys);

                return $updated;
            });
        } catch (Throwable $exception) {
            $this->audit->record($actor, $account, 'line_keys.update_failed', 'failed', $device->switch_resource_id, [
                'device_id' => $device->id,
                'error_type' => $exception::class,
            ], $ipAddress);

            throw $exception;
        }
    }

    private function stringValue(mixed $value): ?string
    {
        return (is_string($value) || is_int($value)) && (string) $value !== '' ? (string) $value : null;
    }
}
