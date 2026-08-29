<?php

namespace App\Domains\Devices\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeviceHotdeskService
{
    public function __construct(
        private readonly SwitchDeviceGateway $gateway,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
        private readonly AuditService $audit,
    ) {}

    /** @return array{users: list<array{id: string, display_name: string, extension: string|null}>, unresolved_count: int} */
    public function memberships(SwitchAccount $account, SwitchDevice $device): array
    {
        $snapshot = is_array($device->switch_json) ? $device->switch_json : [];
        $resourceIds = array_keys(is_array(Arr::get($snapshot, 'hotdesk.users'))
            ? Arr::get($snapshot, 'hotdesk.users')
            : []);
        $extensions = $account->extensions()
            ->whereIn('switch_resource_id', $resourceIds)
            ->orderBy('display_name')
            ->get(['id', 'switch_resource_id', 'display_name', 'extension']);

        return [
            'users' => $extensions->map(static fn (SwitchExtension $extension): array => [
                'id' => $extension->id,
                'display_name' => $extension->display_name,
                'extension' => $extension->extension,
            ])->all(),
            'unresolved_count' => max(0, count($resourceIds) - $extensions->count()),
        ];
    }

    /** @return array{users: list<array{id: string, display_name: string, extension: string|null}>, unresolved_count: int} */
    public function signIn(
        SwitchAccount $account,
        SwitchDevice $device,
        SwitchExtension $extension,
        User $actor,
        ?string $ipAddress = null,
    ): array {
        return $this->mutate($account, $device, $extension, $actor, true, $ipAddress);
    }

    /** @return array{users: list<array{id: string, display_name: string, extension: string|null}>, unresolved_count: int} */
    public function signOut(
        SwitchAccount $account,
        SwitchDevice $device,
        SwitchExtension $extension,
        User $actor,
        ?string $ipAddress = null,
    ): array {
        return $this->mutate($account, $device, $extension, $actor, false, $ipAddress);
    }

    /** @return array{users: list<array{id: string, display_name: string, extension: string|null}>, unresolved_count: int} */
    private function mutate(
        SwitchAccount $account,
        SwitchDevice $device,
        SwitchExtension $extension,
        User $actor,
        bool $signIn,
        ?string $ipAddress,
    ): array {
        try {
            $snapshot = $signIn
                ? $this->gateway->addHotdeskUser($account, $device->switch_resource_id, $extension->switch_resource_id)
                : $this->gateway->removeHotdeskUser($account, $device->switch_resource_id, $extension->switch_resource_id);

            DB::transaction(function () use ($account, $device, $extension, $actor, $signIn, $ipAddress, $snapshot): void {
                $device->forceFill([
                    'switch_json' => $this->redactSensitiveData->handle($snapshot),
                    'last_synced_at' => now(),
                    'sync_status' => ProjectionStatus::Healthy,
                ])->save();
                $this->audit->record(
                    $actor,
                    $account,
                    $signIn ? 'device.hotdesk_user_signed_in' : 'device.hotdesk_user_signed_out',
                    'succeeded',
                    $device->switch_resource_id,
                    ['extension_id' => $extension->id],
                    $ipAddress,
                );
            });

            return $this->memberships($account, $device->refresh());
        } catch (Throwable $exception) {
            $this->audit->record(
                $actor,
                $account,
                $signIn ? 'device.hotdesk_user_sign_in_failed' : 'device.hotdesk_user_sign_out_failed',
                'failed',
                $device->switch_resource_id,
                ['extension_id' => $extension->id, 'error' => $exception->getMessage()],
                $ipAddress,
            );

            throw $exception;
        }
    }
}
