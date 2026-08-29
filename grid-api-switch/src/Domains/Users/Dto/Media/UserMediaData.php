<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\Media;

final readonly class UserMediaData
{
    /**
     * @param  list<string>  $audioCodecs
     * @param  list<string>  $videoCodecs
     * @param  list<string>  $encryptionMethods
     * @param  array<string, mixed>  $preservedOptions
     */
    public function __construct(
        public array $audioCodecs,
        public array $videoCodecs,
        public bool|string $bypassMedia,
        public bool $enforceEncryption,
        public array $encryptionMethods,
        public bool $faxOption,
        public bool $ignoreEarlyMedia,
        public ?int $progressTimeout,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = $this->preservedOptions;
        $data['audio'] = array_replace(
            is_array($data['audio'] ?? null) ? $data['audio'] : [],
            ['codecs' => $this->audioCodecs],
        );
        $data['video'] = array_replace(
            is_array($data['video'] ?? null) ? $data['video'] : [],
            ['codecs' => $this->videoCodecs],
        );
        $data['encryption'] = array_replace(
            is_array($data['encryption'] ?? null) ? $data['encryption'] : [],
            [
                'enforce_security' => $this->enforceEncryption,
                'methods' => $this->encryptionMethods,
            ],
        );
        $data['bypass_media'] = $this->bypassMedia;
        $data['fax_option'] = $this->faxOption;
        $data['ignore_early_media'] = $this->ignoreEarlyMedia;

        if ($this->progressTimeout !== null) {
            $data['progress_timeout'] = $this->progressTimeout;
        }

        return $data;
    }
}
