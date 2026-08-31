<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Voicemail\Dto\VoicemailBoxAdvancedData;
use GridPbx\Switch\Domains\Voicemail\Dto\VoicemailBoxWriteData;
use GridPbx\Switch\Domains\Voicemail\Dto\VoicemailMessageFolder;
use GridPbx\Switch\Domains\Voicemail\Dto\VoicemailNotificationCallbackData;
use GridPbx\Switch\Domains\Voicemail\VoicemailBoxResourceClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class VoicemailBoxResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_it_creates_a_voicemail_box_with_a_bounded_payload(): void
    {
        $client = $this->clientWithResponses([$this->response(['data' => [
            'id' => 'vmbox-1',
            'name' => 'Reception voicemail',
            'mailbox' => '1001',
            'owner_id' => 'user-1',
            'timezone' => 'Asia/Manila',
            'notify_email_addresses' => ['ops@example.com'],
            'transcribe' => true,
            'require_pin' => true,
            'check_if_owner' => false,
            'delete_after_notify' => true,
            'include_message_on_notify' => false,
            'include_transcription_on_notify' => true,
            'media_extension' => 'wav',
            'not_configurable' => true,
            'oldest_message_first' => true,
            'save_after_notify' => false,
            'skip_envelope' => true,
            'skip_greeting' => false,
            'skip_instructions' => true,
            'is_voicemail_ff_rw_enabled' => true,
            'seek_duration_ms' => 15000,
            'flags' => ['external-managed'],
            'notify' => ['callback' => [
                'disabled' => false,
                'number' => '+15559876543',
                'attempts' => 3,
                'interval_s' => 300,
                'timeout_s' => 30,
                'schedule' => [60, 300, 900],
            ]],
        ]])]);

        $snapshot = $client->create('account-1', new VoicemailBoxWriteData(
            name: 'Reception voicemail',
            mailbox: '1001',
            ownerId: 'user-1',
            timezone: 'Asia/Manila',
            notificationEmails: ['ops@example.com'],
            transcribe: true,
            requirePin: true,
            pin: '123456',
            advanced: new VoicemailBoxAdvancedData(
                checkIfOwner: false,
                deleteAfterNotify: true,
                includeMessageOnNotify: false,
                includeTranscriptionOnNotify: true,
                mediaExtension: 'wav',
                notConfigurable: true,
                oldestMessageFirst: true,
                saveAfterNotify: false,
                skipEnvelope: true,
                skipGreeting: false,
                skipInstructions: true,
                fastForwardRewindEnabled: true,
                seekDurationMilliseconds: 15000,
                flags: ['external-managed'],
                notificationCallback: new VoicemailNotificationCallbackData(
                    number: '+15559876543',
                    attempts: 3,
                    intervalSeconds: 300,
                    timeoutSeconds: 30,
                    schedule: [60, 300, 900],
                ),
            ),
        ));

        self::assertSame('vmbox-1', $snapshot->id);
        self::assertSame(['ops@example.com'], $snapshot->notificationEmails);
        self::assertFalse($snapshot->checkIfOwner);
        self::assertSame('wav', $snapshot->mediaExtension);
        self::assertTrue($snapshot->fastForwardRewindEnabled);
        self::assertSame(15000, $snapshot->seekDurationMilliseconds);
        self::assertSame(['external-managed'], $snapshot->flags);
        self::assertSame('+15559876543', $snapshot->notificationCallback?->number);
        self::assertSame('PUT', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/vmboxes', $this->history[0]['request']->getUri()->getPath());
        self::assertSame([
            'data' => [
                'name' => 'Reception voicemail',
                'mailbox' => '1001',
                'owner_id' => 'user-1',
                'notify_email_addresses' => ['ops@example.com'],
                'transcribe' => true,
                'require_pin' => true,
                'timezone' => 'Asia/Manila',
                'pin' => '123456',
                'check_if_owner' => false,
                'delete_after_notify' => true,
                'include_message_on_notify' => false,
                'include_transcription_on_notify' => true,
                'media_extension' => 'wav',
                'not_configurable' => true,
                'oldest_message_first' => true,
                'save_after_notify' => false,
                'skip_envelope' => true,
                'skip_greeting' => false,
                'skip_instructions' => true,
                'is_voicemail_ff_rw_enabled' => true,
                'seek_duration_ms' => 15000,
                'flags' => ['external-managed'],
                'notify' => ['callback' => [
                    'disabled' => false,
                    'number' => '+15559876543',
                    'attempts' => 3,
                    'interval_s' => 300,
                    'timeout_s' => 30,
                    'schedule' => [60, 300, 900],
                ]],
            ],
        ], json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_it_updates_without_sending_an_unchanged_pin(): void
    {
        $client = $this->clientWithResponses([$this->response(['data' => [
            'id' => 'vmbox-1',
            'name' => 'Shared voicemail',
            'mailbox' => '1002',
        ]])]);

        $client->update('account-1', 'vmbox-1', new VoicemailBoxWriteData(
            name: 'Shared voicemail',
            mailbox: '1002',
            advanced: new VoicemailBoxAdvancedData(flags: ['external-managed']),
        ));

        $data = json_decode(
            (string) $this->history[0]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['data'];

        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/vmboxes/vmbox-1', $this->history[0]['request']->getUri()->getPath());
        self::assertArrayNotHasKey('pin', $data);
        self::assertArrayNotHasKey('notify', $data);
        self::assertArrayNotHasKey('owner_id', $data);
        self::assertSame(['external-managed'], $data['flags']);
    }

    public function test_it_serializes_empty_preserved_voicemail_media_as_an_object(): void
    {
        $data = new VoicemailBoxAdvancedData(preservedOptions: ['media' => []]);

        self::assertSame('{"media":{},"flags":[]}', json_encode(
            $data->toSwitchData(),
            JSON_THROW_ON_ERROR,
        ));
    }

    public function test_it_privately_preserves_a_configured_pin_and_unknown_voicemail_fields(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => [
                'id' => 'vmbox-1',
                'name' => 'Shared voicemail',
                'mailbox' => '1002',
                'pin' => '246810',
            ]]),
            $this->response(['data' => [
                'id' => 'vmbox-1',
                'name' => 'Shared voicemail',
                'mailbox' => '1002',
            ]]),
        ]);

        $client->update('account-1', 'vmbox-1', new VoicemailBoxWriteData(
            name: 'Shared voicemail',
            mailbox: '1002',
            preservePin: true,
            advanced: new VoicemailBoxAdvancedData(
                flags: ['external-managed'],
                notificationCallback: new VoicemailNotificationCallbackData(
                    disabled: true,
                    preservedOptions: ['future_callback_option' => true],
                ),
                preservedOptions: [
                    'is_setup' => true,
                    'media' => ['unavailable' => '0123456789abcdef0123456789abcdef'],
                    'future_voicemail_option' => true,
                ],
                notificationPreservedOptions: ['future_notify_option' => 'keep'],
            ),
        ));

        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('POST', $this->history[1]['request']->getMethod());
        $data = json_decode(
            (string) $this->history[1]['request']->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['data'];

        self::assertSame('246810', $data['pin']);
        self::assertTrue($data['is_setup']);
        self::assertSame(
            '0123456789abcdef0123456789abcdef',
            $data['media']['unavailable'],
        );
        self::assertTrue($data['future_voicemail_option']);
        self::assertSame('keep', $data['notify']['future_notify_option']);
        self::assertTrue($data['notify']['callback']['future_callback_option']);
        self::assertTrue($data['notify']['callback']['disabled']);
    }

    public function test_it_deletes_a_voicemail_box(): void
    {
        $client = $this->clientWithResponses([$this->response(['data' => []])]);

        $client->delete('account-1', 'vmbox-1');

        self::assertSame('DELETE', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/vmboxes/vmbox-1', $this->history[0]['request']->getUri()->getPath());
    }

    public function test_it_rejects_an_update_response_for_another_mailbox(): void
    {
        $client = $this->clientWithResponses([$this->response(['data' => [
            'id' => 'vmbox-2',
            'name' => 'Wrong mailbox',
            'mailbox' => '1002',
        ]])]);

        $this->expectException(InvalidSwitchPayloadException::class);
        $client->update('account-1', 'vmbox-1', new VoicemailBoxWriteData('Mailbox', '1001'));
    }

    public function test_it_paginates_typed_voicemail_message_metadata(): void
    {
        $client = $this->clientWithResponses([
            $this->response([
                'data' => [[
                    'media_id' => 'message-1',
                    'folder' => 'new',
                    'caller_id_name' => 'Alice',
                    'caller_id_number' => '+15551234567',
                    'length' => 42000,
                    'timestamp' => 63891972000,
                    'transcription' => ['result' => 'success', 'text' => 'Please call me.'],
                ]],
                'next_start_key' => 'page-2',
            ]),
            $this->response(['data' => [[
                'media_id' => 'message-2',
                'folder' => 'saved',
            ]]]),
        ]);

        $messages = iterator_to_array($client->allMessages('account-1', 'vmbox-1'), false);

        self::assertCount(2, $messages);
        self::assertSame('message-1', $messages[0]->mediaId);
        self::assertSame('Please call me.', $messages[0]->transcriptionText);
        self::assertSame('saved', $messages[1]->folder);
        self::assertSame(
            '/v2/accounts/account-1/vmboxes/vmbox-1/messages?paginate=true&page_size=200',
            $this->history[0]['request']->getUri()->getPath().'?'.$this->history[0]['request']->getUri()->getQuery(),
        );
        self::assertStringContainsString('start_key=page-2', $this->history[1]['request']->getUri()->getQuery());
    }

    public function test_it_streams_audio_and_forwards_a_single_range_header(): void
    {
        $client = $this->clientWithResponses([
            new Response(206, [
                'Content-Type' => 'audio/mpeg',
                'Content-Length' => '4',
                'Content-Range' => 'bytes 0-3/10',
            ], 'MP3!'),
        ]);

        $audio = $client->messageAudio('account-1', 'vmbox-1', 'message-1', 'bytes=0-3');

        self::assertSame(206, $audio->statusCode);
        self::assertSame('audio/mpeg', $audio->contentType);
        self::assertSame(4, $audio->contentLength);
        self::assertSame('bytes 0-3/10', $audio->contentRange);
        self::assertSame('MP3!', (string) $audio->stream);
        self::assertSame('bytes=0-3', $this->history[0]['request']->getHeaderLine('Range'));
        self::assertSame(
            '/v2/accounts/account-1/vmboxes/vmbox-1/messages/message-1/raw',
            $this->history[0]['request']->getUri()->getPath(),
        );
    }

    public function test_it_changes_a_single_message_folder_with_a_bounded_payload(): void
    {
        $client = $this->clientWithResponses([$this->response(['data' => [
            'media_id' => 'message-1',
            'folder' => 'saved',
            'caller_id_name' => 'Alice',
        ]])]);

        $snapshot = $client->changeMessageFolder(
            'account-1',
            'vmbox-1',
            'message-1',
            VoicemailMessageFolder::Saved,
        );

        self::assertSame('saved', $snapshot->folder);
        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertSame(
            '/v2/accounts/account-1/vmboxes/vmbox-1/messages/message-1',
            $this->history[0]['request']->getUri()->getPath(),
        );
        self::assertSame(
            ['data' => ['folder' => 'saved']],
            json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function test_it_changes_multiple_message_folders_and_normalizes_partial_results(): void
    {
        $client = $this->clientWithResponses([$this->response(['data' => [
            'succeeded' => ['message-1'],
            'failed' => [['message-2' => 'not_found']],
        ]])]);

        $result = $client->changeMessagesFolder(
            'account-1',
            'vmbox-1',
            ['message-1', 'message-2'],
            VoicemailMessageFolder::Deleted,
        );

        self::assertSame(['message-1'], $result->succeeded);
        self::assertSame(['message-2' => 'not_found'], $result->failed);
        self::assertSame(
            '/v2/accounts/account-1/vmboxes/vmbox-1/messages',
            $this->history[0]['request']->getUri()->getPath(),
        );
        self::assertSame(
            ['data' => ['folder' => 'deleted', 'messages' => ['message-1', 'message-2']]],
            json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function test_it_patches_the_unavailable_greeting_reference(): void
    {
        $client = $this->clientWithResponses([$this->response(['data' => [
            'id' => 'vmbox-1',
            'name' => 'Reception voicemail',
            'mailbox' => '1001',
            'media' => ['unavailable' => 'media-1'],
        ]])]);

        $snapshot = $client->setUnavailableGreeting('account-1', 'vmbox-1', 'media-1');

        self::assertSame('media-1', $snapshot->toArray()['media']['unavailable']);
        self::assertSame('PATCH', $this->history[0]['request']->getMethod());
        self::assertSame(
            ['data' => ['media' => ['unavailable' => 'media-1']]],
            json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    /** @param list<Response> $responses */
    private function clientWithResponses(array $responses): VoicemailBoxResourceClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $http = new Client(['handler' => $stack]);
        $switch = new SwitchClient(
            $http,
            new SwitchConfig('http://switch.test/v2', 'unused-api-key'),
            new class implements TokenProvider
            {
                public function token(): string
                {
                    return 'test-token';
                }

                public function invalidate(): void {}
            },
        );

        return new VoicemailBoxResourceClient($switch);
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
