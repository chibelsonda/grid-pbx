<?php

declare(strict_types=1);

namespace GridPbx\Switch\Resources;

use GridPbx\Switch\Dto\Callflows\CallflowSnapshot;
use GridPbx\Switch\Dto\Common\EntitySnapshot;
use GridPbx\Switch\Dto\Devices\DeviceSnapshot;
use GridPbx\Switch\Dto\Users\UserSnapshot;
use GridPbx\Switch\Dto\Voicemail\VoicemailBoxSnapshot;

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
