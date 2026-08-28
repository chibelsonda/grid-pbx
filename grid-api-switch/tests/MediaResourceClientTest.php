<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Dto\Media\MediaWriteData;
use GridPbx\Switch\Resources\MediaResourceClient;
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

    /** @param list<Response> $responses */
    private function clientWithResponses(array $responses): MediaResourceClient
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

        return new MediaResourceClient($switch);
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
