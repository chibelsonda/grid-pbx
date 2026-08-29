<?php

namespace App\Domains\Devices\Rules;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Devices\Support\MacAddress;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueDeviceMacAddress implements ValidationRule
{
    public function __construct(
        private readonly ?string $accountId,
        private readonly ?string $ignorePublicDeviceId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $identity = is_string($value) ? MacAddress::identity($value) : null;

        if ($this->accountId === null || $identity === null) {
            return;
        }

        $exists = SwitchDevice::query()
            ->where('switch_account_id', $this->accountId)
            ->where('active_mac_identity', $identity)
            ->when(
                $this->ignorePublicDeviceId !== null,
                fn ($query) => $query->where('id', '!=', $this->ignorePublicDeviceId),
            )
            ->exists();

        if ($exists) {
            $fail('This MAC address is already assigned to another device in this account.');
        }
    }
}
