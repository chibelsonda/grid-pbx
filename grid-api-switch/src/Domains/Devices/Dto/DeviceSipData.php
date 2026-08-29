<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceSipData
{
    public function __construct(
        public ?string $method = null,
        public ?string $username = null,
        private ?string $password = null,
        public ?string $realm = null,
        public ?int $expireSeconds = null,
        public ?string $inviteFormat = null,
        public ?string $ip = null,
        public ?string $number = null,
        public ?string $route = null,
        public ?string $staticRoute = null,
        public ?bool $ignoreCompletedElsewhere = null,
        public ?DeviceCustomSipHeadersData $customSipHeaders = null,
        public ?string $customSipInterface = null,
        public ?string $forward = null,
        public ?string $proxy = null,
        public ?string $staticInvite = null,
        public ?string $transport = null,
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = array_filter([
            'method' => $this->method,
            'username' => $this->username,
            'password' => $this->password,
            'realm' => $this->realm,
            'expire_seconds' => $this->expireSeconds,
            'invite_format' => $this->inviteFormat,
            'ip' => $this->ip,
            'number' => $this->number,
            'route' => $this->route,
            'static_route' => $this->staticRoute,
            'ignore_completed_elsewhere' => $this->ignoreCompletedElsewhere,
            'custom_sip_interface' => $this->customSipInterface,
            'forward' => $this->forward,
            'proxy' => $this->proxy,
            'static_invite' => $this->staticInvite,
            'transport' => $this->transport,
        ], static fn (mixed $value): bool => $value !== null);

        if ($this->customSipHeaders !== null) {
            $data['custom_sip_headers'] = $this->customSipHeaders->toSwitchData();
        }

        return $data;
    }
}
