<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Domains\Conferences\ConferenceResourceClient;
use GridPbx\Switch\Domains\Conferences\Dto\ConferenceWriteData;
use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ConferenceResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function test_maps_conference_roles_and_realtime_status(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [['id' => 'conference-1', 'name' => 'Daily standup']]]),
            $this->response(['data' => [
                'id' => 'conference-1', 'name' => 'Daily standup', 'owner_id' => 'user-1',
                'conference_numbers' => ['7000'],
                'member' => ['numbers' => ['7001'], 'pins' => ['1234'], 'join_muted' => false],
                'moderator' => ['numbers' => ['7099'], 'pins' => ['9876']],
                'max_members_media' => 'media-full', 'play_entry_tone' => 'media-entry', 'play_exit_tone' => false,
                '_read_only' => ['members' => 4, 'moderators' => 1, 'duration' => 90, 'is_locked' => true],
            ]]),
        ]);

        $conference = iterator_to_array((new ConferenceResourceClient($switch))->allDetails('account-1'), false)[0];

        self::assertSame(['7001'], $conference->memberNumbers);
        self::assertTrue($conference->memberPinConfigured);
        self::assertTrue($conference->moderatorPinConfigured);
        self::assertSame('media-full', $conference->maxMembersMediaId);
        self::assertSame('media-entry', $conference->playEntryTone);
        self::assertFalse($conference->playExitTone);
        self::assertSame(4, $conference->activeMembers);
        self::assertTrue($conference->isLocked);
    }

    public function test_writes_pins_only_when_explicitly_set_or_cleared(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => ['id' => 'conference-1', 'name' => 'Standup']]),
            $this->response(['data' => ['id' => 'conference-1', 'name' => 'Standup']]),
        ]);
        $client = new ConferenceResourceClient($switch);
        $client->create('account-1', new ConferenceWriteData(
            name: 'Standup',
            memberNumbers: ['7001'],
            maxMembersMediaId: 'media-full',
            playEntryTone: true,
            playExitTone: false,
        ));
        $client->update('account-1', 'conference-1', new ConferenceWriteData(name: 'Standup', moderatorPin: '9876', clearMemberPin: true));

        $create = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $update = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('pins', $create['data']['member']);
        self::assertSame('media-full', $create['data']['max_members_media']);
        self::assertTrue($create['data']['play_entry_tone']);
        self::assertFalse($create['data']['play_exit_tone']);
        self::assertSame([], $update['data']['member']['pins']);
        self::assertSame(['9876'], $update['data']['moderator']['pins']);
        self::assertSame('/v2/accounts/account-1/conferences/conference-1', $this->history[1]['request']->getUri()->getPath());
    }

    public function test_explicitly_clears_the_conference_full_prompt(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => ['id' => 'conference-1', 'name' => 'Standup']]),
        ]);

        (new ConferenceResourceClient($switch))->update(
            'account-1',
            'conference-1',
            new ConferenceWriteData(name: 'Standup', clearMaxMembersMedia: true),
        );

        $update = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('max_members_media', $update['data']);
        self::assertNull($update['data']['max_members_media']);
    }

    /** @param list<Response> $responses */
    private function switchWithResponses(array $responses): SwitchClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SwitchClient(new Client(['handler' => $stack]), new SwitchConfig('http://switch.test/v2', 'unused'), new class implements TokenProvider
        {
            public function token(): string
            {
                return 'test-token';
            }

            public function invalidate(): void {}
        });
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
