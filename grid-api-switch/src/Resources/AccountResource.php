<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use GridPbx\Switch\Dto\CallflowSnapshot;
use GridPbx\Switch\Dto\DeviceSnapshot;
use GridPbx\Switch\Dto\EntitySnapshot;
use GridPbx\Switch\Dto\UserSnapshot;
use GridPbx\Switch\Dto\VoicemailBoxSnapshot;

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
