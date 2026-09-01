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
        $client->update('account-1', 'conference-1', new ConferenceWriteData(name: 'Standup', moderatorPins: ['9876', '8765'], clearMemberPin: true));

        $create = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $update = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('pins', $create['data']['member']);
        self::assertSame('media-full', $create['data']['max_members_media']);
        self::assertTrue($create['data']['play_entry_tone']);
        self::assertFalse($create['data']['play_exit_tone']);
        self::assertSame([], $update['data']['member']['pins']);
        self::assertSame(['9876', '8765'], $update['data']['moderator']['pins']);
        self::assertSame('PATCH', $this->history[1]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/conferences/conference-1', $this->history[1]['request']->getUri()->getPath());
    }

    public function test_update_uses_recursive_patch_and_omits_unchanged_write_only_pins(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => ['id' => 'conference-1', 'name' => 'Standup']]),
        ]);

        (new ConferenceResourceClient($switch))->update(
            'account-1',
            'conference-1',
            new ConferenceWriteData(name: 'Standup', language: null),
        );
        $update = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('PATCH', $this->history[0]['request']->getMethod());
        self::assertArrayNotHasKey('pins', $update['data']['member']);
        self::assertArrayNotHasKey('pins', $update['data']['moderator']);
        self::assertArrayHasKey('language', $update['data']);
        self::assertNull($update['data']['language']);
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

        self::assertSame('PATCH', $this->history[0]['request']->getMethod());
        self::assertArrayHasKey('max_members_media', $update['data']);
        self::assertNull($update['data']['max_members_media']);
    }

    public function test_write_data_enforces_documented_gridpbx_safety_limits(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConferenceWriteData(name: 'Standup', maxParticipants: 10001);
    }

    public function test_sends_room_lock_and_unlock_commands_to_the_conference_resource(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => []], 202),
            $this->response(['data' => []], 202),
        ]);
        $client = new ConferenceResourceClient($switch);

        $client->setLocked('account-1', 'conference-1', true);
        $client->setLocked('account-1', 'conference-1', false);

        $lock = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $unlock = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('PUT', $this->history[0]['request']->getMethod());
        self::assertSame('/v2/accounts/account-1/conferences/conference-1', $this->history[0]['request']->getUri()->getPath());
        self::assertSame(['data' => ['action' => 'lock']], $lock);
        self::assertSame(['data' => ['action' => 'unlock']], $unlock);
    }

    public function test_maps_a_safe_participant_snapshot_and_sends_a_participant_command(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => [[
                'participant_id' => 42,
                'caller_id_name' => 'Ada Lovelace',
                'caller_id_number' => '1001',
                'duration' => 37,
                'conference_channel_vars' => [
                    'is_moderator' => true,
                    'speak' => false,
                    'hear' => true,
                    'call_id' => 'private-call-id',
                ],
                'switch_hostname' => 'private-switch-node',
            ]]]),
            $this->response(['data' => []], 202),
        ]);
        $client = new ConferenceResourceClient($switch);

        $participant = $client->participants('account-1', 'conference-1')[0]->toArray();
        $client->controlParticipant('account-1', 'conference-1', '42', 'mute');

        self::assertSame([
            'id' => '42',
            'display_name' => 'Ada Lovelace',
            'number' => '1001',
            'is_moderator' => true,
            'can_speak' => false,
            'can_hear' => true,
            'duration_seconds' => 37,
        ], $participant);
        self::assertArrayNotHasKey('call_id', $participant);
        self::assertArrayNotHasKey('switch_hostname', $participant);

        $command = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('/v2/accounts/account-1/conferences/conference-1/participants/42', $this->history[1]['request']->getUri()->getPath());
        self::assertSame(['data' => ['action' => 'mute']], $command);
    }

    public function test_sends_a_kazoo_room_wide_participant_command(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => []], 202),
        ]);
        $client = new ConferenceResourceClient($switch);

        $client->controlParticipants('account-1', 'conference-1', 'deaf');

        $command = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('/v2/accounts/account-1/conferences/conference-1/participants', $this->history[0]['request']->getUri()->getPath());
        self::assertSame(['data' => ['action' => 'deaf']], $command);
    }

    public function test_plays_media_to_a_room_or_one_participant_using_the_kazoo_command_shape(): void
    {
        $switch = $this->switchWithResponses([
            $this->response(['data' => []], 202),
            $this->response(['data' => []], 202),
        ]);
        $client = new ConferenceResourceClient($switch);

        $client->playMedia('account-1', 'conference-1', 'media-1');
        $client->playMedia('account-1', 'conference-1', 'media-1', '42');

        $roomCommand = json_decode((string) $this->history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $participantCommand = json_decode((string) $this->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('/v2/accounts/account-1/conferences/conference-1', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('/v2/accounts/account-1/conferences/conference-1/participants/42', $this->history[1]['request']->getUri()->getPath());
        self::assertSame(['data' => ['action' => 'play', 'data' => ['media_id' => 'media-1']]], $roomCommand);
        self::assertSame($roomCommand, $participantCommand);
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
    private function response(array $payload, int $status = 200): Response
    {
        return new Response($status, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR));
    }
}
