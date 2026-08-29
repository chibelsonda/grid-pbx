<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\Hotdesk;

use InvalidArgumentException;

final readonly class UserHotdeskData
{
    public function __construct(
        public bool $enabled = false,
        public ?string $id = null,
        public bool $keepLoggedInElsewhere = false,
        public bool $requirePin = false,
        public ?string $pin = null,
        public bool $preservePin = false,
    ) {
        if ($this->id !== null
            && (preg_match('/^[0-9+#*]{4,15}$/', $this->id) !== 1)) {
            throw new InvalidArgumentException('Switch user hotdesk ID must contain 4 to 15 dial-pad characters.');
        }

        if ($this->pin !== null
            && preg_match('/^[0-9]{4,15}$/', $this->pin) !== 1) {
            throw new InvalidArgumentException('Switch user hotdesk PIN must contain 4 to 15 digits.');
        }

        if ($this->pin !== null && $this->preservePin) {
            throw new InvalidArgumentException('A hotdesk PIN cannot be set and preserved in the same request.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = [
            'enabled' => $this->enabled,
            'keep_logged_in_elsewhere' => $this->keepLoggedInElsewhere,
            'require_pin' => $this->requirePin,
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        if ($this->pin !== null) {
            $data['pin'] = $this->pin;
        }

        return $data;
    }
}
