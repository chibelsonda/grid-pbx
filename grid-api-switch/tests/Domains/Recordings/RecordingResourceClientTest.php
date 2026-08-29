<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Recordings\RecordingResourceClient;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class RecordingResourceClientTest extends TestCase
{
    private array $history = [];

    public function test_it_paginates_bounded_recording_metadata(): void
    {
        $client = $this->client([
            $this->response(['data' => [['id' => '202608-recording-1', 'call_id' => 'call-1', 'start' => 63986630400, 'duration_ms' => 4200, '_read_only' => ['content_types' => ['audio/mpeg']]]], 'next_start_key' => 'next']),
            $this->response(['data' => [['id' => '202608-recording-2', 'call_id' => 'call-2', 'start' => 63986630500, 'duration' => 7]]]),
        ], 1);
        $recordings = iterator_to_array($client->all('account-1', 1787875200, 1787961600), false);
        parse_str($this->history[0]['request']->getUri()->getQuery(), $firstQuery);
        parse_str($this->history[1]['request']->getUri()->getQuery(), $secondQuery);

        self::assertSame(['202608-recording-1', '202608-recording-2'], array_map(fn ($recording) => $recording->id, $recordings));
        self::assertSame(4200, $recordings[0]->durationMilliseconds);
        self::assertTrue($recordings[0]->hasAudio);
        self::assertSame(1787875200 + 62167219200, (int) $firstQuery['created_from']);
        self::assertSame('next', $secondQuery['start_key']);
    }

    public function test_it_streams_recording_audio_with_range_and_inline_headers(): void
    {
        $client = $this->client([new Response(206, ['Content-Type' => 'audio/mpeg', 'Content-Length' => '4', 'Content-Range' => 'bytes 0-3/10'], 'MP3!')]);
        $audio = $client->audio('account-1', '202608-recording-1', 'bytes=0-3');
        parse_str($this->history[0]['request']->getUri()->getQuery(), $query);

        self::assertSame(206, $audio->statusCode);
        self::assertSame('audio/mpeg', $this->history[0]['request']->getHeaderLine('Accept'));
        self::assertSame('bytes=0-3', $this->history[0]['request']->getHeaderLine('Range'));
        self::assertSame('true', $query['inline']);
    }

    private function client(array $responses, int $pageSize = 200): RecordingResourceClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $switch = new SwitchClient(new Client(['handler' => $stack]), new SwitchConfig('http://switch.test/v2', 'unused'), new class implements TokenProvider
        {
            public function token(): string
            {
                return 'test-token';
            }

            public function invalidate(): void {}
        });

        return new RecordingResourceClient($switch, $pageSize);
    }

    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
