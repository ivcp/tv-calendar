<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Config;
use App\Services\CloudflareCacheService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class CloudflareCacheServiceTest extends TestCase
{
    public function testPurgePathsSendsFullUrlsForEveryHost(): void
    {
        $history = [];
        $service = new CloudflareCacheService(
            $this->getClient([$this->successResponse()], $history),
            $this->config()
        );

        $this->assertTrue($service->purgePaths(['/', '/favicon.ico']));

        $this->assertCount(1, $history);
        $this->assertSame(
            ['files' => [
                'https://example.test/',
                'https://example.test/favicon.ico',
                'https://www.example.test/',
                'https://www.example.test/favicon.ico',
            ]],
            $this->payload($history)
        );
        $this->assertSame('Bearer test-token', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame(
            'https://api.cloudflare.com/client/v4/zones/test-zone/purge_cache',
            (string) $history[0]['request']->getUri()
        );
    }

    public function testPurgePrefixesSendsHostAndPathWithoutScheme(): void
    {
        $history = [];
        $service = new CloudflareCacheService(
            $this->getClient([$this->successResponse()], $history),
            $this->config()
        );

        $this->assertTrue($service->purgePrefixes(['/discover', '/shows/']));

        $this->assertSame(
            ['prefixes' => [
                'example.test/discover',
                'example.test/shows/',
                'www.example.test/discover',
                'www.example.test/shows/',
            ]],
            $this->payload($history)
        );
    }

    public function testPurgeAllSendsTheConfiguredHosts(): void
    {
        $history = [];
        $service = new CloudflareCacheService(
            $this->getClient([$this->successResponse()], $history),
            $this->config()
        );

        $this->assertTrue($service->purgeAll());

        $this->assertSame(
            ['hosts' => ['example.test', 'www.example.test']],
            $this->payload($history)
        );
    }

    public function testItDoesNotCallCloudflareWithoutConfiguration(): void
    {
        $history = [];
        $service = new CloudflareCacheService(
            $this->getClient([], $history),
            $this->config(['zone_id' => '', 'api_token' => ''])
        );

        $this->assertFalse($service->purgeAll());
        $this->assertCount(0, $history);
    }

    public function testItReturnsFalseWhenCloudflareRejectsThePurge(): void
    {
        $history = [];
        $service = new CloudflareCacheService(
            $this->getClient([
                new Response(200, [], '{"success":false,"errors":[{"code":1,"message":"denied"}]}'),
            ], $history),
            $this->config()
        );

        $this->assertFalse($service->purgeAll());
        $this->assertCount(1, $history);
    }

    public function testItReturnsFalseOnTransportErrors(): void
    {
        $history = [];
        $service = new CloudflareCacheService(
            $this->getClient([
                new ConnectException(
                    'timeout',
                    new Request('POST', 'https://api.cloudflare.com/client/v4/zones/test-zone/purge_cache')
                ),
            ], $history),
            $this->config()
        );

        $this->assertFalse($service->purgeAll());
    }

    private function successResponse(): Response
    {
        return new Response(200, [], '{"success":true,"errors":[],"messages":[],"result":null}');
    }

    private function config(array $overrides = []): Config
    {
        return new Config([
            'app_environment' => 'development',
            'cloudflare' => array_merge([
                'zone_id' => 'test-zone',
                'api_token' => 'test-token',
                'hosts' => ['example.test', 'www.example.test'],
            ], $overrides),
        ]);
    }

    private function getClient(array $responses, array &$history): Client
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($history));

        return new Client(['handler' => $handlerStack]);
    }

    private function payload(array $history): array
    {
        return json_decode((string) $history[0]['request']->getBody(), true);
    }
}
