<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WebhookService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class WebhookServiceTest extends TestCase
{
    public function testSkipsRequestWhenUrlIsEmpty(): void
    {
        $history = [];
        $client = $this->getClient([], $history);
        $webhookService = new WebhookService($client);

        $webhookService->send(null, 'test');
        $webhookService->send('', 'test');

        $this->assertCount(0, $history);
    }

    public function testSendsContentAsJson(): void
    {
        $history = [];
        $client = $this->getClient([new Response(204)], $history);

        (new WebhookService($client))->send('https://discord.test/webhook', 'hello');

        $this->assertCount(1, $history);
        $this->assertSame(
            ['content' => 'hello'],
            json_decode((string) $history[0]['request']->getBody(), true)
        );
    }

    public function testDoesNotThrowOnErrorResponse(): void
    {
        $history = [];
        $client = $this->getClient([new Response(500, [], '')], $history);

        (new WebhookService($client))->send('https://discord.test/webhook', 'hello');

        $this->assertCount(1, $history);
    }

    public function testDoesNotThrowOnTransportError(): void
    {
        $history = [];
        $client = $this->getClient([
            new ConnectException('timeout', new Request('POST', 'https://discord.test/webhook')),
        ], $history);

        (new WebhookService($client))->send('https://discord.test/webhook', 'hello');

        $this->assertCount(1, $history);
    }

    private function getClient(array $responses, array &$history): Client
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($history));

        return new Client(['handler' => $handlerStack]);
    }
}
