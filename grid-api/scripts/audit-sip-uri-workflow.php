<?php

declare(strict_types=1);

use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Devices\DeviceResourceClient;
use GridPbx\Switch\Domains\Devices\Dto\DeviceAdvancedData;
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
$deviceId = null;

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

$writeData = static fn (string $stage, string $route, bool $excluded): DeviceWriteData => new DeviceWriteData(
    name: "GridPBX SIP URI {$stage} {$stamp}",
    deviceType: 'sip_uri',
    enabled: true,
    sip: new DeviceSipData(
        inviteFormat: 'route',
        route: $route,
    ),
    advanced: new DeviceAdvancedData(
        excludeFromContactList: $excluded,
    ),
);

$assertMinimalWrite = static function (DeviceWriteData $write, string $route, bool $excluded) use ($assertSame): void {
    $payload = $write->toSwitchData();

    $assertSame(
        ['name', 'device_type', 'enabled', 'sip', 'contact_list'],
        array_keys($payload),
        'SIP URI write contains fields outside the supported workflow.',
    );
    $assertSame(
        ['invite_format' => 'route', 'route' => $route],
        $payload['sip'] ?? null,
        'SIP URI write contains registered-endpoint SIP fields.',
    );
    $assertSame(
        ['exclude' => $excluded],
        $payload['contact_list'] ?? null,
        'SIP URI contact-list option mismatch.',
    );
};

try {
    $createRoute = 'sip:gridpbx-audit-created@example.invalid';
    $createWrite = $writeData('Created', $createRoute, false);
    $assertMinimalWrite($createWrite, $createRoute, false);

    $created = $devices->create($accountId, $createWrite);
    $deviceId = $created->id;
    $createdRead = $devices->get($accountId, $deviceId)->toArray();
    $assertSame('sip_uri', Arr::get($createdRead, 'device_type'), 'SIP URI create type mismatch.');
    $assertSame('route', Arr::get($createdRead, 'sip.invite_format'), 'SIP URI create invite format mismatch.');
    $assertSame($createRoute, Arr::get($createdRead, 'sip.route'), 'SIP URI create route mismatch.');
    $assertSame(false, Arr::get($createdRead, 'contact_list.exclude', false), 'SIP URI create contact-list option mismatch.');
    fwrite(STDOUT, "verified sip_uri create\n");

    $editRoute = 'sip:gridpbx-audit-edited@example.invalid';
    $editWrite = $writeData('Edited', $editRoute, true);
    $assertMinimalWrite($editWrite, $editRoute, true);

    $devices->update($accountId, $deviceId, $editWrite);
    $editedRead = $devices->get($accountId, $deviceId)->toArray();
    $assertSame($editRoute, Arr::get($editedRead, 'sip.route'), 'SIP URI edit route mismatch.');
    $assertSame(true, Arr::get($editedRead, 'contact_list.exclude'), 'SIP URI edit contact-list option mismatch.');
    fwrite(STDOUT, "verified sip_uri edit\n");

    $clearWrite = $writeData('Cleared', $editRoute, false);
    $assertMinimalWrite($clearWrite, $editRoute, false);

    $devices->update($accountId, $deviceId, $clearWrite);
    $clearedRead = $devices->get($accountId, $deviceId)->toArray();
    $assertSame($editRoute, Arr::get($clearedRead, 'sip.route'), 'SIP URI required route was not preserved.');
    $assertSame(false, Arr::get($clearedRead, 'contact_list.exclude'), 'SIP URI contact-list option was not cleared.');
    fwrite(STDOUT, "verified sip_uri clear\n");
} finally {
    if (is_string($deviceId)) {
        $devices->delete($accountId, $deviceId);
        fwrite(STDOUT, "removed temporary sip_uri device\n");
    }
}
