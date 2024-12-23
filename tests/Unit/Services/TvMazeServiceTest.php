<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DataObjects\EpisodeData;
use App\DataObjects\ShowData;
use App\Services\TvMazeService;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class TvMazeServiceTest extends TestCase
{

    private function getTvMazeService(array $responses): TvMazeService
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        return new TvMazeService($client);
    }

    public function test_gets_updated_shows(): void
    {
        $tvMazeService = $this->getTvMazeService([
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], '{"1" : 123, "2": 456}')
        ]);
        $updated = $tvMazeService->getUpdatedShowIDs();
        $this->assertCount(2, $updated);
        $this->assertSame(1, $updated[0]);
        $this->assertSame(2, $updated[1]);
        $this->assertSame(1, $tvMazeService->getPause());
    }

    public function test_updated_shows_returns_empty_arr_on_404_and_500(): void
    {
        $tvMazeService = $this->getTvMazeService([
            new Response(404, ['Content-Type' => 'application/json; charset=UTF-8'], ''),
            new Response(500, [], ''),
        ]);
        $updated = $tvMazeService->getUpdatedShowIDs();
        $this->assertEmpty($updated);
        $updated = $tvMazeService->getUpdatedShowIDs();
        $this->assertEmpty($updated);
    }

    public function test_updated_shows_retries_on_429(): void
    {
        $tvMazeService = $this->getTvMazeService([
            new Response(429, [], ''),
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], '{"1" : 123, "2": 456}'),
        ]);
        $updated = $tvMazeService->getUpdatedShowIDs();
        $this->assertCount(2, $updated);
        $this->assertSame(1, $tvMazeService->getPause());
    }

    public function test_updated_shows_retries_on_429_and_fail(): void
    {
        $tvMazeService = $this->getTvMazeService([
            new Response(429, [], ''),
            new Response(404, [], ''),
        ]);
        $updated = $tvMazeService->getUpdatedShowIDs();
        $this->assertEmpty($updated);
        $this->assertSame(1, $tvMazeService->getPause());
    }


    public function test_get_show(): void
    {
        $tvMazeService = $this->getTvMazeService([
            new Response(
                200,
                ['Content-Type' => 'application/json; charset=UTF-8'],
                '{
                "id": 1, 
                "name": "test name", 
                "status": "running", 
                "weight": 100, 
                "externals": {
                    "imdb": "tt999"
                }, 
                "genres": ["Drama", "Romance"], 
                "premiered": "2024-12-01",
                "ended": "2024-12-10",
                "officialSite": "www.test.com",
                "network": {
                    "name": "test network",
                    "country": {"name": "test country"}
                },
                "webChannel": {
                    "name": "test web network",
                    "country": {"name": "test web country"}
                },
                "summary": "test summary",
                "runtime": 60,
                "image": {
                    "medium": "medium.link",
                    "original": null
                }
                }'
            )
        ]);
        $show = $tvMazeService->getShow(1);
        $this->assertInstanceOf(ShowData::class, $show);
        $this->assertSame('test name', $show->name);
        $this->assertSame(100, $show->weight);
        $this->assertSame('medium.link', $show->imageMedium);
        $this->assertNull($show->imageOriginal);
    }

    public function test_get_show_returns_null_on_500_and_404(): void
    {
        $tvMazeService = $this->getTvMazeService([
            new Response(500, [], ''),
            new Response(404, [], '')

        ]);
        $show = $tvMazeService->getShow(1);
        $this->assertNull($show);
        $show = $tvMazeService->getShow(1);
        $this->assertNull($show);
    }


    public function test_get_show_retries_on_429(): void
    {
        $tvMazeService = $this->getTvMazeService([
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
        ]);
        $show = $tvMazeService->getShow(1);
        $this->assertInstanceOf(ShowData::class, $show);
        $this->assertSame('test name', $show->name);
        $this->assertSame(1, $tvMazeService->getPause());
    }

    public function test_get_episodes(): void
    {
        $tvMazeService = $this->getTvMazeService([
            new Response(
                200,
                ['Content-Type' => 'application/json; charset=UTF-8'],
                '[
                {
                "id": 1, 
                "name": "test episode name", 
                "season": 1, 
                "number": 42, 
                "type": "regular", 
                "airstamp": "2013-07-02T02:00:00+00:00",                
                "summary": "short summary",
                "runtime": 60,
                "image": null 
                }              
                ]'
            )
        ]);
        $episodes = $tvMazeService->getEpisodes(11);
        $this->assertCount(1, $episodes);
        $this->assertInstanceOf(EpisodeData::class, $episodes[0]);
        $this->assertSame('test episode name', $episodes[0]->episodeName);
        $this->assertSame(42, $episodes[0]->episodeNumber);
        $date = new DateTime("2013-07-02T02:00:00+00:00");
        $this->assertTrue($date == $episodes[0]->airstamp);
        $this->assertSame(11, $episodes[0]->tvMazeShowId);
        $this->assertNull($episodes[0]->imageMedium);
    }

    public function test_get_episodes_file(): void
    {
        $episodes = file_get_contents(__DIR__ . '/episodes_test.json');

        $tvMazeService = $this->getTvMazeService([
            new Response(
                200,
                ['Content-Type' => 'application/json; charset=UTF-8'],
                $episodes
            )
        ]);
        $episodes = $tvMazeService->getEpisodes(1);
        $this->assertCount(1920, $episodes);
        $this->assertIsList($episodes);
        $this->assertSame('The Fire', $episodes[1]->episodeName);
    }

    public function test_get_episodes_returns_empty_arr_on_500_and_404(): void
    {
        $tvMazeService = $this->getTvMazeService([
            new Response(500, [], ''),
            new Response(404, [], '')
        ]);
        $episodes = $tvMazeService->getEpisodes(1);
        $this->assertEmpty($episodes);
        $episodes = $tvMazeService->getEpisodes(1);
        $this->assertEmpty($episodes);
    }

    public function test_get_episodes_retries_on_429(): void
    {
        $tvMazeService = $this->getTvMazeService([
            new Response(429, [], ''),
            new Response(
                200,
                ['Content-Type' => 'application/json; charset=UTF-8'],
                '[
                {
                "id": 1, 
                "name": "test episode name", 
                "season": 1, 
                "number": 42, 
                "type": "regular", 
                "airstamp": "2013-07-02T02:00:00+00:00",                
                "summary": "short summary",
                "runtime": 60,
                "image": null
                }
                ]'
            )
        ]);
        $episodes = $tvMazeService->getEpisodes(1);
        $this->assertCount(1, $episodes);
        $this->assertInstanceOf(EpisodeData::class, $episodes[0]);
        $this->assertSame(1, $tvMazeService->getPause());
    }

    public function test_get_multiple_shows(): void
    {

        $tvMazeService = $this->getTvMazeService([
            new Response(429, [], ''),
            new Response(
                200,
                ['Content-Type' => 'application/json; charset=UTF-8'],
                '{
                "id": 1, 
                "name": "test name 1", 
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
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json; charset=UTF-8'],
                '{
                "id": 2, 
                "name": "test name 2", 
                "status": "running", 
                "weight": 99, 
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
            ),
        ]);

        $shows = $tvMazeService->getShows([1, 2]);
        $this->assertCount(2, $shows);
        $this->assertSame('test name 2', $shows[1]->name);
        $this->assertSame(100, $shows[0]->weight);
    }
}
