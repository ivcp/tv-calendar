<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\ShowData;
use Error;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class TvMazeService
{
    private string $baseUri = 'https://api.tvmaze.com';

    private int $retry429 = 1;

    public function __construct(private readonly Client $client) {}

    public function getShow(int $id): ?ShowData
    {
        $url = $this->baseUri . '/shows/' . "$id";
        $response = $this->get($url);


        if ($response->getStatusCode() === 429) {
            $this->pauseAndIncreaseRetry();
            return $this->getShow($id);
        }

        if (!$this->statusOK($response, __METHOD__)) {
            return null;
        }


        $body = $response->getBody();
        $data = json_decode((string)$body);

        return new ShowData(
            tvMazeId: $data->id,
            imdbId: $data?->externals?->imdb,
            genres: $data?->genres,
            status: $data->status,
            premiered: $data?->premiered,
            ended: $data?->ended,
            officialSite: $data?->officialSite,
            weight: $data->weight,
            networkName: $data?->network?->name,
            networkCountry: $data?->network?->country?->name,
            webChannelName: $data?->webChannel?->name,
            webChannelCountry: $data?->webChannel?->country,
            summary: $data?->summary,
            name: $data->name,
            runtime: $data?->runtime,
            imageMedium: $data?->image?->medium,
            imageOriginal: $data?->image?->original
        );
    }


    public function getUpdatedShows(): array
    {
        $url = $this->baseUri . '/updates/shows?since=day';
        $response = $this->get($url);

        if ($response->getStatusCode() === 429) {
            $this->pauseAndIncreaseRetry();
            return $this->getUpdatedShows();
        }

        if (!$this->statusOK($response, __METHOD__)) {
            return [];
        }

        $body = $response->getBody();
        $updatedShows = json_decode((string)$body, true);
        return $updatedShows;
    }



    private function get(string $url): ResponseInterface
    {
        return $this->client->get($url, ['http_errors' => false]);
    }

    private function logError(string $methodName, int $code, string $message): void
    {
        error_log("$methodName error with status code $code: " . $message);
    }

    private function pauseAndIncreaseRetry(): void
    {
        sleep($this->retry429);
        $this->retry429 = $this->retry429 * 2;
    }


    private function statusOK(ResponseInterface $response, string $method): bool
    {
        if ($response->getStatusCode() !== 200) {
            $this->logError($method, $response->getStatusCode(),  $response->getReasonPhrase());
            return false;
        }

        return true;
    }



    //getEpisodes
}
