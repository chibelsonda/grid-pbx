<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use GridPbx\Switch\Shared\Authentication\TokenProvider;
use GridPbx\Switch\Domains\Faxes\Dto\FaxBoxWriteData;
use GridPbx\Switch\Domains\Faxes\FaxBoxResourceClient;
use GridPbx\Switch\Domains\Faxes\FaxMessageResourceClient;
use GridPbx\Switch\SwitchClient;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class FaxResourceClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */ private array $history = [];

    public function test_fax_box_client_maps_configuration_and_notifications(): void
    {
        $switch = $this->switch([
            $this->response(['data' => [['id' => 'box-1', 'name' => 'Main fax']]]),
            $this->response(['data' => ['id' => 'box-1', 'name' => 'Main fax', 'owner_id' => 'user-1', 'retries' => 3, 'media' => ['fax_option' => true], 'smtp_email_address' => 'abcd@fax.test', 'notifications' => ['inbound' => ['email' => ['send_to' => ['ops@example.test']]]]]]),
            $this->response(['data' => ['id' => 'box-2', 'name' => 'Billing fax']]),
        ]);
        $client = new FaxBoxResourceClient($switch);
        $boxes = iterator_to_array($client->allDetails('account-1'), false);
        $created = $client->create('account-1', new FaxBoxWriteData(name: 'Billing fax', outboundNotificationEmails: ['billing@example.test']));
        $body = json_decode((string) $this->history[2]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('user-1', $boxes[0]->ownerId); self::assertTrue($boxes[0]->t38Enabled);
        self::assertSame(['ops@example.test'], $boxes[0]->inboundNotificationEmails); self::assertSame('box-2', $created->id);
        self::assertSame(['billing@example.test'], $body['data']['notifications']['outbound']['email']['send_to']);
    }

    public function test_fax_message_client_paginates_bounded_metadata(): void
    {
        $switch = $this->switch([
            $this->response(['data' => [['id' => '202608-fax-1', 'folder' => 'inbox', 'from_number' => '+12025550101', 'created' => 63986630400, 'rx_result' => ['success' => true, 'pages_received' => 2], '_attachments' => ['fax.pdf' => ['content_type' => 'application/pdf', 'length' => 1200]]]], 'next_start_key' => 'next']),
            $this->response(['data' => [['id' => '202608-fax-2', 'folder' => 'inbox', 'from_number' => '+12025550102', 'created' => 63986630500]]]),
        ]);
        $client = new FaxMessageResourceClient($switch, 1);
        $messages = iterator_to_array($client->all('account-1', 'inbox', 1787875200, 1787961600), false);
        parse_str($this->history[0]['request']->getUri()->getQuery(), $query); parse_str($this->history[1]['request']->getUri()->getQuery(), $nextQuery);

        self::assertSame(['202608-fax-1', '202608-fax-2'], array_map(fn ($fax) => $fax->id, $messages));
        self::assertSame(2, $messages[0]->pages); self::assertTrue($messages[0]->hasDocument);
        self::assertSame(1787875200 + 62167219200, (int) $query['created_from']); self::assertSame('next', $nextQuery['start_key']);
    }

    public function test_fax_document_uses_protected_binary_request(): void
    {
        $client = new FaxMessageResourceClient($this->switch([new Response(206, ['Content-Type' => 'application/pdf', 'Content-Length' => '4', 'Content-Range' => 'bytes 0-3/10'], '%PDF')]));
        $document = $client->document('account-1', 'inbox', '202608-fax-1', 'bytes=0-3');
        parse_str($this->history[0]['request']->getUri()->getQuery(), $query);

        self::assertSame(206, $document->statusCode); self::assertStringContainsString('application/pdf', $this->history[0]['request']->getHeaderLine('Accept'));
        self::assertSame('bytes=0-3', $this->history[0]['request']->getHeaderLine('Range')); self::assertSame('inline', $query['disposition']);
    }

    /** @param list<Response> $responses */
    private function switch(array $responses): SwitchClient
    {
        $this->history = []; $stack = HandlerStack::create(new MockHandler($responses)); $stack->push(Middleware::history($this->history));
        return new SwitchClient(new Client(['handler' => $stack]), new SwitchConfig('http://switch.test/v2', 'unused'), new class implements TokenProvider { public function token(): string { return 'test-token'; } public function invalidate(): void {} });
    }
    /** @param array<string, mixed> $payload */ private function response(array $payload): Response { return new Response(200, [], json_encode($payload + ['status' => 'success'], JSON_THROW_ON_ERROR)); }
}
