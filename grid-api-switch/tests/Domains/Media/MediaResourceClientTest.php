<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Media\Dto\MediaTtsWriteData;
use GridPbx\Switch\Domains\Media\Dto\MediaWriteData;
use GridPbx\Switch\Domains\Media\MediaResourceClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

final class MediaResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_it_creates_and_uploads_audio_with_bounded_payloads(): void
    {
        $client = $this->clientWithResponses([
            $this->response(['data' => ['id' => 'media-1', 'name' => 'Mailbox greeting']]),
            $this->response(['data' => [
                'id' => 'media-1',
            ]]),
            $this->response(['data' => [
                'id' => 'media-1',
                'name' => 'Mailbox greeting',
                'content_type' => 'audio/mpeg',
                'content_length' => 4,
            ]]),
        ]);

        $created = $client->create('account-1', new MediaWriteData(
            name: 'Mailbox greeting',
            description: 'greeting.mp3',
            sourceId: 'vmbox-1',
            sourceType: 'voicemail',
        ));
        $uploaded = $client->upload(
            'account-1',
            $created->id,
            Utils::streamFor('MP3!'),
            'audio/mpeg',
            4,
        );

        self::assertSame('media-1', $uploaded->id);
        self::assertSame('PUT', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/media', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('POST', $this->history[1]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/media/media-1/raw', $this->history[1]['request']->getUri()->getPath());
        self::assertSame('audio/mpeg', $this->history[1]['request']->getHeaderLine('Content-Type'));
        self::assertSame('4', $this->history[1]['request']->getHeaderLine('Content-Length'));
        self::assertSame('MP3!', (string) $this->history[1]['request']->getBody());
        self::assertSame('GET', $this->history[2]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/media/media-1', $this->history[2]['request']->getUri()->getPath());
    }

    public function test_it_streams_raw_media_with_range_support(): void
    {
        $client = $this->clientWithResponses([
            new Response(206, [
                'Content-Type' => 'audio/wav',
                'Content-Length' => '4',
                'Content-Range' => 'bytes 0-3/10',
            ], 'WAVE'),
        ]);

        $audio = $client->raw('account-1', 'media-1', 'bytes=0-3');

        self::assertSame(206, $audio->statusCode);
        self::assertSame('audio/wav', $audio->contentType);
        self::assertSame('bytes=0-3', $this->history[0]['request']->getHeaderLine('Range'));
    }

    public function test_it_paginates_media_details_and_updates_metadata(): void
    {
        $client = $this->clientWithResponses([
            $this->response([
                'data' => [['id' => 'media-1']],
                'next_start_key' => 'next page',
            ]),
            $this->response(['data' => [
                'id' => 'media-1',
                'name' => 'Hold music',
                'language' => 'en-us',
            ]]),
            $this->response(['data' => [['id' => 'media-2']]]),
            $this->response(['data' => [
                'id' => 'media-2',
                'name' => 'After hours',
            ]]),
            $this->response(['data' => [
                'id' => 'media-1',
                'name' => 'Main hold music',
                'language' => 'en-gb',
                'media_source' => 'upload',
                'streamable' => true,
            ]]),
        ], pageSize: 1);

        $media = iterator_to_array($client->allDetails('account-1'));
        $updated = $client->update('account-1', 'media-1', new MediaWriteData(
            name: 'Main hold music',
            language: 'en-gb',
            contentType: 'audio/mpeg',
            promptId: 'system_prompt',
            sourceId: '0123456789abcdef0123456789abcdef',
            sourceType: 'callflow',
            tts: new MediaTtsWriteData(
                text: 'Welcome to GridPBX.',
                voice: 'female/en-US',
            ),
        ));
        parse_str($this->history[2]['request']->getUri()->getQuery(), $secondPageQuery);

        self::assertSame(['media-1', 'media-2'], array_map(
            static fn ($snapshot): string => $snapshot->id,
            $media,
        ));
        self::assertSame('Main hold music', $updated->name);
        self::assertSame('en-gb', $updated->language);
        self::assertSame('next page', $secondPageQuery['start_key']);
        self::assertSame('POST', $this->history[4]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/media/media-1', $this->history[4]['request']->getUri()->getPath());
        self::assertSame('en-gb', json_decode((string) $this->history[4]['request']->getBody(), true)['data']['language']);
        self::assertSame(
            [
                'text' => 'Welcome to GridPBX.',
                'voice' => 'female/en-US',
            ],
            json_decode((string) $this->history[4]['request']->getBody(), true)['data']['tts'],
        );
        self::assertSame(
            'system_prompt',
            json_decode((string) $this->history[4]['request']->getBody(), true)['data']['prompt_id'],
        );
    }

    /** @param list<Response> $responses */
    private function clientWithResponses(array $responses, int $pageSize = 200): MediaResourceClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $switch = new SwitchClient(
            new Client(['handler' => $stack]),
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

        return new MediaResourceClient($switch, $pageSize);
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
