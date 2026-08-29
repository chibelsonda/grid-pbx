<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Voicemail\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class VoicemailMessageBulkResult
{
    /** @var list<string> */
    public array $succeeded;

    /** @var array<string, string> */
    public array $failed;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $succeeded = $data['succeeded'] ?? null;
        $failed = $data['failed'] ?? null;

        if (! is_array($succeeded) || ! is_array($failed)) {
            throw new InvalidSwitchPayloadException('Switch voicemail bulk response must contain succeeded and failed arrays.');
        }

        $this->succeeded = $this->stringList($succeeded, 'succeeded');
        $this->failed = $this->failureMap($failed);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values, string $name): array
    {
        $strings = [];

        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new InvalidSwitchPayloadException(sprintf('Switch voicemail bulk %s entries must be non-empty strings.', $name));
            }

            $strings[] = $value;
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param  array<mixed>  $failures
     * @return array<string, string>
     */
    private function failureMap(array $failures): array
    {
        $normalized = [];

        foreach ($failures as $messageId => $failure) {
            if (is_string($messageId) && is_string($failure) && $messageId !== '' && $failure !== '') {
                $normalized[$messageId] = $failure;

                continue;
            }

            if (! is_array($failure) || $failure === []) {
                throw new InvalidSwitchPayloadException('Switch voicemail bulk failed entries must map message IDs to reasons.');
            }

            foreach ($failure as $nestedMessageId => $reason) {
                if (! is_string($nestedMessageId) || $nestedMessageId === '' || ! is_string($reason) || $reason === '') {
                    throw new InvalidSwitchPayloadException('Switch voicemail bulk failed entries must map message IDs to reasons.');
                }

                $normalized[$nestedMessageId] = $reason;
            }
        }

        return $normalized;
    }
}
