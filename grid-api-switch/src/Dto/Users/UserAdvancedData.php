<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Users;

final readonly class UserAdvancedData
{
    public function __construct(
        public ?string $language = null,
        public ?string $presenceId = null,
        public ?bool $callWaiting = null,
        public ?bool $doNotDisturb = null,
        public ?bool $excludeFromContactList = null,
        public ?string $outboundPrivacy = null,
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = array_filter([
            'language' => $this->language,
            'presence_id' => $this->presenceId,
        ], static fn (?string $value): bool => $value !== null);

        if ($this->callWaiting !== null) {
            $data['call_waiting'] = ['enabled' => $this->callWaiting];
        }

        if ($this->doNotDisturb !== null) {
            $data['do_not_disturb'] = ['enabled' => $this->doNotDisturb];
        }

        if ($this->excludeFromContactList !== null) {
            $data['contact_list'] = ['exclude' => $this->excludeFromContactList];
        }

        if ($this->outboundPrivacy !== null) {
            $data['caller_id_options'] = ['outbound_privacy' => $this->outboundPrivacy];
        }

        return $data;
    }
}
