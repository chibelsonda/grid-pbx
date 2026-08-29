<?php

declare(strict_types=1);

use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Devices\DeviceResourceClient;
use GridPbx\Switch\Domains\Devices\Dto\DeviceAdvancedData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceCallForwardData;
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

$writeData = static function (
    string $deviceType,
    string $stage,
    bool $enabled,
    string $number,
    bool $excluded,
): DeviceWriteData {
    $edited = $stage === 'Edited';

    return new DeviceWriteData(
        name: "GridPBX {$deviceType} {$stage}",
        deviceType: $deviceType,
        enabled: $enabled,
        callForward: new DeviceCallForwardData(
            enabled: $enabled,
            number: $number,
            directCallsOnly: $edited,
            failover: $edited,
            ignoreEarlyMedia: ! $edited,
            keepCallerId: ! $edited,
            requireKeypress: ! $edited,
            substitute: ! $edited,
        ),
        advanced: new DeviceAdvancedData(
            excludeFromContactList: $excluded,
        ),
    );
};

$assertMinimalWrite = static function (
    DeviceWriteData $write,
    string $deviceType,
    bool $enabled,
    string $number,
    bool $excluded,
) use ($assertSame): void {
    $payload = $write->toSwitchData();

    $assertSame(
        ['name', 'device_type', 'enabled', 'call_forward', 'contact_list'],
        array_keys($payload),
        "{$deviceType} write contains endpoint-only fields.",
    );
    $assertSame($enabled, $payload['enabled'] ?? null, "{$deviceType} enabled mismatch.");
    $assertSame($enabled, Arr::get($payload, 'call_forward.enabled'), "{$deviceType} forwarding enabled mismatch.");
    $assertSame($number, Arr::get($payload, 'call_forward.number'), "{$deviceType} forwarding number mismatch.");
    $assertSame($excluded, Arr::get($payload, 'contact_list.exclude'), "{$deviceType} contact-list mismatch.");
};

foreach (['cellphone', 'landline'] as $deviceType) {
    $deviceId = null;

    try {
        $createNumber = '+15551234567';
        $createWrite = $writeData($deviceType, 'Created', true, $createNumber, false);
        $assertMinimalWrite($createWrite, $deviceType, true, $createNumber, false);
        $created = $devices->create($accountId, $createWrite);
        $deviceId = $created->id;
        $createdRead = $devices->get($accountId, $deviceId)->toArray();
        $assertSame(true, Arr::get($createdRead, 'enabled'), "{$deviceType} create enabled mismatch.");
        $assertSame(true, Arr::get($createdRead, 'call_forward.enabled'), "{$deviceType} create forwarding state mismatch.");
        $assertSame($createNumber, Arr::get($createdRead, 'call_forward.number'), "{$deviceType} create number mismatch.");
        fwrite(STDOUT, "verified {$deviceType} create\n");

        $editNumber = '+15557654321';
        $editWrite = $writeData($deviceType, 'Edited', true, $editNumber, true);
        $assertMinimalWrite($editWrite, $deviceType, true, $editNumber, true);
        $devices->update($accountId, $deviceId, $editWrite);
        $editedRead = $devices->get($accountId, $deviceId)->toArray();
        $assertSame($editNumber, Arr::get($editedRead, 'call_forward.number'), "{$deviceType} edit number mismatch.");
        $assertSame(true, Arr::get($editedRead, 'call_forward.direct_calls_only'), "{$deviceType} advanced forwarding mismatch.");
        $assertSame(true, Arr::get($editedRead, 'contact_list.exclude'), "{$deviceType} edit contact-list mismatch.");
        fwrite(STDOUT, "verified {$deviceType} edit\n");

        $clearWrite = $writeData($deviceType, 'Cleared', false, '', false);
        $assertMinimalWrite($clearWrite, $deviceType, false, '', false);
        $devices->update($accountId, $deviceId, $clearWrite);
        $clearedRead = $devices->get($accountId, $deviceId)->toArray();
        $assertSame(false, Arr::get($clearedRead, 'enabled'), "{$deviceType} clear enabled mismatch.");
        $assertSame(false, Arr::get($clearedRead, 'call_forward.enabled'), "{$deviceType} clear forwarding state mismatch.");
        $assertSame('', Arr::get($clearedRead, 'call_forward.number'), "{$deviceType} number was not cleared.");
        $assertSame(false, Arr::get($clearedRead, 'contact_list.exclude'), "{$deviceType} contact-list option was not cleared.");
        fwrite(STDOUT, "verified {$deviceType} disable and clear\n");
    } finally {
        if (is_string($deviceId)) {
            $devices->delete($accountId, $deviceId);
            fwrite(STDOUT, "removed temporary {$deviceType} device\n");
        }
    }
}
