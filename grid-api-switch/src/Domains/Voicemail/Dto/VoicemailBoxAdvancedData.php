<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Voicemail\Dto;

final readonly class VoicemailBoxAdvancedData
{
    /** @param list<string> $flags */
    public function __construct(
        public ?bool $checkIfOwner = null,
        public ?bool $deleteAfterNotify = null,
        public ?bool $includeMessageOnNotify = null,
        public ?bool $includeTranscriptionOnNotify = null,
        public ?string $mediaExtension = null,
        public ?bool $notConfigurable = null,
        public ?bool $oldestMessageFirst = null,
        public ?bool $saveAfterNotify = null,
        public ?bool $skipEnvelope = null,
        public ?bool $skipGreeting = null,
        public ?bool $skipInstructions = null,
        public ?bool $fastForwardRewindEnabled = null,
        public ?int $seekDurationMilliseconds = null,
        public array $flags = [],
        public ?VoicemailNotificationCallbackData $notificationCallback = null,
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = array_filter([
            'check_if_owner' => $this->checkIfOwner,
            'delete_after_notify' => $this->deleteAfterNotify,
            'include_message_on_notify' => $this->includeMessageOnNotify,
            'include_transcription_on_notify' => $this->includeTranscriptionOnNotify,
            'media_extension' => $this->mediaExtension,
            'not_configurable' => $this->notConfigurable,
            'oldest_message_first' => $this->oldestMessageFirst,
            'save_after_notify' => $this->saveAfterNotify,
            'skip_envelope' => $this->skipEnvelope,
            'skip_greeting' => $this->skipGreeting,
            'skip_instructions' => $this->skipInstructions,
            'is_voicemail_ff_rw_enabled' => $this->fastForwardRewindEnabled,
            'seek_duration_ms' => $this->seekDurationMilliseconds,
        ], static fn (mixed $value): bool => $value !== null);

        $data['flags'] = array_values(array_unique(array_filter(
            $this->flags,
            static fn (mixed $flag): bool => is_string($flag) && $flag !== '',
        )));

        if ($this->notificationCallback !== null) {
            $data['notify'] = ['callback' => $this->notificationCallback->toSwitchData()];
        }

        return $data;
    }
}
