<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DataObjects\ShowData;
use App\Services\TvMazeService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class TvMazeServiceTest extends TestCase
{

    protected function getClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public function test_gets_updated_shows(): void
    {
        $tvMazeService = new TvMazeService($this->getClient([
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], '{"1" : 123, "2": 456}')
        ]));
        $updated = $tvMazeService->getUpdatedShows();
        $this->assertCount(2, $updated);
        $this->assertArrayHasKey(2, $updated);
        $this->assertSame(456, $updated[2]);
    }

    public function test_updated_shows_returns_empty_arr_on_404(): void
    {
        $tvMazeService = new TvMazeService($this->getClient([
            new Response(404, ['Content-Type' => 'application/json; charset=UTF-8'], ''),
        ]));
        $updated = $tvMazeService->getUpdatedShows();
        $this->assertEmpty($updated);
    }

    public function test_updated_shows_retries_on_429(): void
    {
        $tvMazeService = new TvMazeService($this->getClient([
            new Response(429, [], ''),
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], '{"1" : 123, "2": 456}'),
        ]));
        $updated = $tvMazeService->getUpdatedShows();
        $this->assertCount(2, $updated);
    }

    public function test_updated_shows_retries_on_429_and_fail(): void
    {
        $tvMazeService = new TvMazeService($this->getClient([
            new Response(429, [], ''),
            new Response(404, [], ''),
        ]));
        $updated = $tvMazeService->getUpdatedShows();
        $this->assertEmpty($updated);
    }

    public function test_updated_shows_returns_empty_arr_on_500(): void
    {
        $tvMazeService = new TvMazeService($this->getClient([
            new Response(500, [], ''),
        ]));
        $updated = $tvMazeService->getUpdatedShows();
        $this->assertEmpty($updated);
    }

    public function test_get_show(): void
    {
        $tvMazeService = new TvMazeService($this->getClient([
            new Response(
                200,
                ['Content-Type' => 'application/json; charset=UTF-8'],
                '{
                "id": 1, 
                "name": "test name", 
                "status": "running", 
                "weight": 100, 
                "externals": null, 
                "genres": null, 
                "premiered": null,
                "ended": null,
                "officialSite": null,
                "network": null,
                "webChannel": null,
                "summary": null,
                "runtime": null,
                "image": null
                }'
            )
        ]));
        $show = $tvMazeService->getShow(1);
        $this->assertInstanceOf(ShowData::class, $show);
        $this->assertSame('test name', $show->name);
        $this->assertSame(100, $show->weight);
        $this->assertNull($show->imageMedium);
    }

    public function test_get_show_returns_null_on_500(): void
    {
        $tvMazeService = new TvMazeService($this->getClient([
            new Response(500, [], '')
        ]));
        $show = $tvMazeService->getShow(1);
        $this->assertNull($show);
    }

    public function test_get_show_returns_null_on_404(): void
    {
        $tvMazeService = new TvMazeService($this->getClient([
            new Response(404, [], '')
        ]));
        $show = $tvMazeService->getShow(1);
        $this->assertNull($show);
    }
    public function test_get_show_retries_on_429(): void
    {
        $tvMazeService = new TvMazeService($this->getClient([
            new Response(429, [], ''),
            new Response(
                200,
                ['Content-Type' => 'application/json; charset=UTF-8'],
                '{
                "id": 1, 
                "name": "test name", 
                "status": "running", 
                "weight": 100, 
                "externals": null, 
                "genres": null, 
                "premiered": null,
                "ended": null,
                "officialSite": null,
                "network": null,
                "webChannel": null,
                "summary": null,
                "runtime": null,
                "image": null
                }'
            )
        ]));
        $show = $tvMazeService->getShow(1);
        $this->assertInstanceOf(ShowData::class, $show);
        $this->assertSame('test name', $show->name);
    }
}
