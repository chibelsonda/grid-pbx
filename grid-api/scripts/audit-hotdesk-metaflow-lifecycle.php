<?php

declare(strict_types=1);

use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Callflows\CallflowResourceClient;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowCreateData;
use GridPbx\Switch\Domains\Devices\DeviceResourceClient;
use GridPbx\Switch\Domains\Devices\Dto\DeviceMetaflowsData;
use GridPbx\Switch\Domains\Devices\Dto\DeviceWriteData;
use GridPbx\Switch\Domains\Media\Dto\MediaWriteData;
use GridPbx\Switch\Domains\Media\MediaResourceClient;
use GridPbx\Switch\Domains\Users\Dto\Credentials\UserCredentialsData;
use GridPbx\Switch\Domains\Users\Dto\Hotdesk\UserHotdeskData;
use GridPbx\Switch\Domains\Users\Dto\UserWriteData;
use GridPbx\Switch\Domains\Users\UserResourceClient;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$account = SwitchAccount::query()->firstOrFail();
$accountId = $account->switch_account_id;
$users = $app->make(UserResourceClient::class);
$devices = $app->make(DeviceResourceClient::class);
$media = $app->make(MediaResourceClient::class);
$callflows = $app->make(CallflowResourceClient::class);
$stamp = gmdate('ymdHis');
$extension = '8'.$stamp;
$hotdeskId = '9'.substr($stamp, -7);
$username = 'gridpbx.audit.'.$stamp;
$userId = null;
$mediaId = null;
$callflowId = null;
$deviceId = null;
$cleanupFailures = [];

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

$deviceData = static function (
    string $name,
    string $ownerId,
    array $numbers,
): DeviceWriteData {
    return new DeviceWriteData(
        name: $name,
        deviceType: 'sip_device',
        enabled: true,
        ownerId: $ownerId,
        metaflows: new DeviceMetaflowsData(
            bindingDigit: '*',
            digitTimeout: 2200,
            listenOn: 'both',
            numbers: $numbers,
            patterns: [],
        ),
    );
};

try {
    $createdUser = $users->create($accountId, new UserWriteData(
        firstName: 'GridPBX',
        lastName: "Audit {$stamp}",
        extension: $extension,
        hotdesk: new UserHotdeskData(
            enabled: true,
            id: $hotdeskId,
            keepLoggedInElsewhere: false,
            requirePin: true,
            pin: '2468',
        ),
        credentials: new UserCredentialsData(
            username: $username,
            password: "GridPBX-audit-{$stamp}!",
            requirePasswordUpdate: true,
        ),
    ));
    $userId = $createdUser->id;
    $assertSame(true, $createdUser->hotdeskEnabled, 'User hotdesk create enabled state mismatch.');
    $assertSame($hotdeskId, $createdUser->hotdeskId, 'User hotdesk create ID mismatch.');
    $assertSame(true, $createdUser->hotdeskPinConfigured, 'User hotdesk create PIN mismatch.');
    $assertSame($username, $createdUser->username, 'User login create username mismatch.');
    $assertSame(true, $createdUser->requirePasswordUpdate, 'User login forced-update state mismatch.');

    $updatedUser = $users->update($accountId, $userId, new UserWriteData(
        firstName: 'GridPBX',
        lastName: "Audit Edited {$stamp}",
        extension: $extension,
        hotdesk: new UserHotdeskData(
            enabled: true,
            id: $hotdeskId,
            keepLoggedInElsewhere: true,
            requirePin: true,
            preservePin: true,
        ),
        credentials: new UserCredentialsData(
            username: $username,
            requirePasswordUpdate: false,
        ),
    ));
    $assertSame(true, $updatedUser->hotdeskKeepLoggedInElsewhere, 'User hotdesk edit state mismatch.');
    $assertSame(true, $updatedUser->hotdeskPinConfigured, 'User hotdesk preserved PIN mismatch.');
    $assertSame($username, $updatedUser->username, 'Unchanged user login was not preserved.');
    $assertSame(false, $updatedUser->requirePasswordUpdate, 'User forced-password-change clear mismatch.');

    $createdMedia = $media->create($accountId, new MediaWriteData(
        name: "GridPBX Hotdesk Audit Media {$stamp}",
        description: 'Temporary resource used for recursive Device metaflow verification.',
    ));
    $mediaId = $createdMedia->id;

    $createdCallflow = $callflows->create($accountId, new CallflowCreateData(
        name: "GridPBX Hotdesk Audit Callflow {$stamp}",
        destinationModule: 'user',
        destinationResourceId: $userId,
        entryNumbers: [$extension],
    ));
    $callflowId = $createdCallflow->id;

    $createdDevice = $devices->create($accountId, $deviceData(
        "GridPBX Hotdesk Audit Device {$stamp}",
        $userId,
        [
            '51' => [
                'module' => 'play',
                'data' => ['id' => $mediaId, 'leg' => 'both'],
                'children' => [
                    '_' => [
                        'module' => 'callflow',
                        'data' => ['id' => $callflowId],
                        'children' => (object) [],
                    ],
                ],
            ],
        ],
    ));
    $deviceId = $createdDevice->id;
    $createdRead = $devices->get($accountId, $deviceId)->toArray();
    $assertSame($mediaId, data_get($createdRead, 'metaflows.numbers.51.data.id'), 'Recursive metaflow media link mismatch.');
    $assertSame($callflowId, data_get($createdRead, 'metaflows.numbers.51.children._.data.id'), 'Recursive metaflow callflow link mismatch.');

    $signedIn = $devices->addHotdeskUser($accountId, $deviceId, $userId)->toArray();
    $assertSame(true, array_key_exists($userId, (array) data_get($signedIn, 'hotdesk.users', [])), 'Device hotdesk sign-in mismatch.');

    $signedOut = $devices->removeHotdeskUser($accountId, $deviceId, $userId)->toArray();
    $assertSame(false, array_key_exists($userId, (array) data_get($signedOut, 'hotdesk.users', [])), 'Device hotdesk sign-out mismatch.');

    $edited = $devices->update($accountId, $deviceId, $deviceData(
        "GridPBX Hotdesk Audit Device Edited {$stamp}",
        $userId,
        [
            '52' => [
                'module' => 'callflow',
                'data' => ['id' => $callflowId],
                'children' => [
                    '_' => [
                        'module' => 'move',
                        'data' => [
                            'device_id' => $deviceId,
                            'auto_answer' => false,
                            'can_call_self' => true,
                            'dial_strategy' => 'simultaneous',
                        ],
                        'children' => [
                            '_' => [
                                'module' => 'play',
                                'data' => ['id' => $mediaId, 'leg' => 'self'],
                                'children' => (object) [],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ))->toArray();
    $assertSame($callflowId, data_get($edited, 'metaflows.numbers.52.data.id'), 'Edited metaflow callflow link mismatch.');
    $assertSame($deviceId, data_get($edited, 'metaflows.numbers.52.children._.data.device_id'), 'Edited metaflow device link mismatch.');
    $assertSame($mediaId, data_get($edited, 'metaflows.numbers.52.children._.children._.data.id'), 'Edited metaflow media link mismatch.');

    $cleared = $devices->update($accountId, $deviceId, $deviceData(
        "GridPBX Hotdesk Audit Device Cleared {$stamp}",
        $userId,
        [],
    ))->toArray();
    $assertSame([], (array) data_get($cleared, 'metaflows.numbers', []), 'Recursive metaflow clear mismatch.');

    $clearedUser = $users->update($accountId, $userId, new UserWriteData(
        firstName: 'GridPBX',
        lastName: "Audit Cleared {$stamp}",
        extension: $extension,
        hotdesk: new UserHotdeskData(
            enabled: false,
            id: $hotdeskId,
        ),
        credentials: new UserCredentialsData,
    ));
    $assertSame(false, $clearedUser->hotdeskEnabled, 'User hotdesk clear enabled state mismatch.');
    $assertSame(false, $clearedUser->hotdeskPinConfigured, 'User hotdesk PIN was not cleared.');
    $assertSame(null, $clearedUser->username, 'User login credentials were not cleared.');

    echo json_encode([
        'status' => 'passed',
        'account' => $account->id,
        'verified' => [
            'user_hotdesk_create_edit_preserve_clear',
            'user_login_create_unchanged_password_omission_force_update_clear',
            'device_hotdesk_sign_in_sign_out',
            'recursive_media_callflow_device_metaflows_create_edit_clear',
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    foreach ([
        'device' => static fn () => $deviceId === null ? null : $devices->delete($accountId, $deviceId),
        'callflow' => static fn () => $callflowId === null ? null : $callflows->delete($accountId, $callflowId),
        'media' => static fn () => $mediaId === null ? null : $media->delete($accountId, $mediaId),
        'user' => static fn () => $userId === null ? null : $users->delete($accountId, $userId),
    ] as $resource => $cleanup) {
        try {
            $cleanup();
        } catch (Throwable $exception) {
            $cleanupFailures[$resource] = $exception->getMessage();
        }
    }

    if ($cleanupFailures !== []) {
        fwrite(STDERR, 'Cleanup failures: '.json_encode($cleanupFailures, JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
