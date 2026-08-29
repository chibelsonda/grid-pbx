<?php

declare(strict_types=1);

use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Devices\DeviceResourceClient;
use GridPbx\Switch\Domains\Devices\Dto\DeviceMediaData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceOutboundFlagsData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceSipData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceWriteData;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$account = SwitchAccount::query()->firstOrFail();
$accountId = $account->switch_account_id;
$devices = $app->make(DeviceResourceClient::class);
$stamp = gmdate('YmdHis');
$faxOptionTypes = ['sip_device', 'softphone', 'fax', 'ata'];
$completedElsewhereTypes = ['sip_device', 'softphone'];

$assertSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s Expected %s, received %s.',
            $message,
            json_encode($expected, JSON_THROW_ON_ERROR),
            json_encode($actual, JSON_THROW_ON_ERROR),
        ));
    }
};

$writeData = static function (string $deviceType, string $stage) use (
    $completedElsewhereTypes,
    $faxOptionTypes,
    $stamp,
): DeviceWriteData {
    $enabled = $stage === 'edited';
    $supportsFaxOption = in_array($deviceType, $faxOptionTypes, true);
    $supportsCompletedElsewhere = in_array($deviceType, $completedElsewhereTypes, true);

    return new DeviceWriteData(
        name: "GridPBX {$deviceType} capability {$stage}",
        deviceType: $deviceType,
        enabled: true,
        sip: new DeviceSipData(
            method: 'password',
            username: substr("cap_{$deviceType}_{$stamp}", 0, 32),
            password: "gridpbx-{$stamp}-secret",
            inviteFormat: 'contact',
            ignoreCompletedElsewhere: $supportsCompletedElsewhere ? $enabled : null,
        ),
        media: $supportsFaxOption ? new DeviceMediaData(faxOption: $enabled) : null,
        outboundFlags: $deviceType === 'fax' ? new DeviceOutboundFlagsData(['fax']) : null,
    );
};

foreach (['sip_device', 'smartphone', 'softphone', 'fax', 'ata'] as $deviceType) {
    $deviceId = null;
    $supportsFaxOption = in_array($deviceType, $faxOptionTypes, true);
    $supportsCompletedElsewhere = in_array($deviceType, $completedElsewhereTypes, true);

    try {
        foreach (['created', 'edited', 'cleared'] as $stage) {
            $write = $writeData($deviceType, $stage);
            $payload = $write->toSwitchData();
            $expected = $stage === 'edited';

            $assertSame(
                $supportsCompletedElsewhere,
                Arr::has($payload, 'sip.ignore_completed_elsewhere'),
                "{$deviceType} completed-elsewhere write capability mismatch.",
            );
            $assertSame(
                $supportsFaxOption,
                Arr::has($payload, 'media.fax_option'),
                "{$deviceType} T.38 write capability mismatch.",
            );

            if ($deviceType === 'fax') {
                $assertSame(['fax'], $payload['outbound_flags'] ?? null, 'Fax outbound flag missing.');
            }

            if ($deviceId === null) {
                $snapshot = $devices->create($accountId, $write);
                $deviceId = $snapshot->id;
            } else {
                $devices->update($accountId, $deviceId, $write);
            }

            $read = $devices->get($accountId, $deviceId)->toArray();

            if ($supportsCompletedElsewhere) {
                $assertSame(
                    $expected,
                    Arr::get($read, 'sip.ignore_completed_elsewhere'),
                    "{$deviceType} {$stage} completed-elsewhere mismatch.",
                );
            }

            if ($supportsFaxOption) {
                $assertSame(
                    $expected,
                    Arr::get($read, 'media.fax_option'),
                    "{$deviceType} {$stage} T.38 mismatch.",
                );
            }

            if ($deviceType === 'fax') {
                $flags = Arr::get($read, 'outbound_flags', []);
                if (! is_array($flags) || ! in_array('fax', Arr::flatten($flags), true)) {
                    throw new RuntimeException("Fax outbound flag missing after {$stage}.");
                }
            }

            fwrite(STDOUT, "verified {$deviceType} {$stage}\n");
        }
    } finally {
        if (is_string($deviceId)) {
            $devices->delete($accountId, $deviceId);
            fwrite(STDOUT, "removed temporary {$deviceType} device\n");
        }
    }
}
