<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\EpisodeData;
use App\DataObjects\ShowData;
use DateTime;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class TvMazeService
{
    private string $baseUri = 'https://api.tvmaze.com';

    private int $pause = 1;

    public function __construct(private readonly Client $client) {}


    //TODO: logging

    /**
     * Get list of updated shows with timestamps
     *
     **/
    public function getUpdatedShowIDs(): array
    {
        $url = $this->baseUri . '/updates/shows?since=day';
        $response = $this->get($url);

        if ($response->getStatusCode() === 429) {
            $this->pauseAndIncrease();
            return $this->getUpdatedShowIDs();
        }

        if (!$this->statusOK($response, __METHOD__)) {
            $this->resetPause();
            return [];
        }

        $updatedShows = array_keys(json_decode((string)$response->getBody(), true));
        $this->resetPause();
        return $updatedShows;
    }


    /**
     * Get shows for provided IDs
     *
     * @return ShowData[]
     **/
    public function getShows(array $ids): array
    {
        $shows = [];
        foreach ($ids as $id) {
            $show = $this->getShow($id);
            if ($show) {
                $shows[] = $show;
            }
        }
        return $shows;
    }



    /**
     * Get show with id
     *
     * @return ?ShowData
     **/
    public function getShow(int $id): ?ShowData
    {
        $url = $this->baseUri . '/shows/' . "$id";
        $response = $this->get($url);


        if ($response->getStatusCode() === 429) {
            $this->pauseAndIncrease();
            return $this->getShow($id);
        }

        if (!$this->statusOK($response, __METHOD__)) {
            $this->resetPause();
            return null;
        }


        $data = json_decode((string)$response->getBody());
        $this->resetPause();

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
            webChannelCountry: $data?->webChannel?->country?->name,
            summary: $data?->summary,
            name: $data->name,
            runtime: $data?->runtime,
            imageMedium: $data?->image?->medium,
            imageOriginal: $data?->image?->original
        );
    }

    /**
     * Get all episodes of a show
     *
     * @return EpisodeData[]
     **/
    public function getEpisodes(int $showId): array
    {
        $url = $this->baseUri . '/shows/' . "$showId" . '/episodes?specials=1';
        $response = $this->get($url);


        if ($response->getStatusCode() === 429) {
            $this->pauseAndIncrease();
            return $this->getEpisodes($showId);
        }

        if (!$this->statusOK($response, __METHOD__)) {
            $this->resetPause();
            return [];
        }

        $episodes = json_decode((string)$response->getBody());
        $this->resetPause();

        return array_map(function ($episode) use ($showId) {
            $airstamp = null;
            if ($episode?->airstamp) {
                $airstamp = new DateTime($episode->airstamp);
            }
            return new EpisodeData(
                tvMazeShowId: $showId,
                tvMazeEpisodeId: $episode->id,
                episodeName: $episode->name,
                seasonNumber: $episode?->season,
                episodeNumber: $episode?->number,
                episodeSummary: $episode?->summary,
                type: $episode?->type,
                airstamp: $airstamp,
                runtime: $episode?->runtime,
                imageMedium: $episode?->image?->medium,
                imageOriginal: $episode?->image?->original
            );
        }, $episodes);
    }



    private function get(string $url): ResponseInterface
    {
        return $this->client->get($url, ['http_errors' => false]);
    }

    private function logError(string $methodName, int $code, string $message): void
    {
        error_log("$methodName error with status code $code: " . $message);
    }

    private function pauseAndIncrease(): void
    {
        sleep($this->getPause());
        $this->pause = $this->pause * 2;
    }

    public function getPause(): int
    {
        return $this->pause;
    }

    private function resetPause(): void
    {
        $this->pause = 1;
    }


    private function statusOK(ResponseInterface $response, string $method): bool
    {
        if ($response->getStatusCode() !== 200) {
            $this->logError($method, $response->getStatusCode(),  $response->getReasonPhrase());
            return false;
        }

        return true;
    }
}
