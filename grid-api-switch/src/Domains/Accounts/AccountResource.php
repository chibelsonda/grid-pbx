<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts;

use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use GridPbx\Switch\Domains\Devices\Dto\DeviceSnapshot;
use GridPbx\Switch\Domains\Users\Dto\UserSnapshot;
use GridPbx\Switch\Domains\Voicemail\Dto\VoicemailBoxSnapshot;
use GridPbx\Switch\Shared\Dto\EntitySnapshot;

enum AccountResource: string
{
    case Users = 'users';
    case Devices = 'devices';
    case VoicemailBoxes = 'vmboxes';
    case Callflows = 'callflows';

    /** @param array<string, mixed> $data */
    public function snapshot(array $data): EntitySnapshot
    {
        return match ($this) {
            self::Users => new UserSnapshot($data),
            self::Devices => new DeviceSnapshot($data),
            self::VoicemailBoxes => new VoicemailBoxSnapshot($data),
            self::Callflows => new CallflowSnapshot($data),
        };
    }
}
