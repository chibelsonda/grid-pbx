<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto\Hotdesk;

final readonly class DeviceHotdeskMembership
{
    /** @param array<string, mixed> $users */
    public function __construct(public array $users) {}

    /** @return array<string, object> */
    public function toSwitchUsers(): array
    {
        $users = [];

        foreach ($this->users as $userId => $settings) {
            if (! is_string($userId) || $userId === '') {
                continue;
            }

            $users[$userId] = is_object($settings)
                ? $settings
                : (object) (is_array($settings) ? $settings : []);
        }

        return $users;
    }
}
