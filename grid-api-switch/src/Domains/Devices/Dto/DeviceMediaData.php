<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceMediaData
{
    /**
     * @param  list<string>|null  $audioCodecs
     * @param  list<string>|null  $videoCodecs
     * @param  list<string>|null  $encryptionMethods
     */
    public function __construct(
        public ?array $audioCodecs = null,
        public ?array $videoCodecs = null,
        public bool|string|null $bypassMedia = null,
        public ?bool $enforceEncryption = null,
        public ?array $encryptionMethods = null,
        public ?bool $faxOption = null,
        public ?bool $ignoreEarlyMedia = null,
        public ?int $progressTimeout = null,
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = array_filter([
            'bypass_media' => $this->bypassMedia,
            'fax_option' => $this->faxOption,
            'ignore_early_media' => $this->ignoreEarlyMedia,
            'progress_timeout' => $this->progressTimeout,
        ], static fn (mixed $value): bool => $value !== null);

        if ($this->audioCodecs !== null) {
            $data['audio'] = ['codecs' => $this->audioCodecs];
        }

        if ($this->videoCodecs !== null) {
            $data['video'] = ['codecs' => $this->videoCodecs];
        }

        if ($this->enforceEncryption !== null || $this->encryptionMethods !== null) {
            $data['encryption'] = array_filter([
                'enforce_security' => $this->enforceEncryption,
                'methods' => $this->encryptionMethods,
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $data;
    }
}
