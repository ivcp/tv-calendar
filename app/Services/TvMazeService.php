<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\EpisodeData;
use App\DataObjects\ShowData;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class TvMazeService
{
    private const MAX_ATTEMPTS = 4;

    private string $baseUri = 'https://api.tvmaze.com';

    private int $pause = 1;

    public function __construct(
        private readonly Client $client,
        private readonly int $retryDelaySeconds = 1,
        private readonly string $userAgent = 'tv-calendar/1.0'
    ) {}


    /**
     * Get list of updated shows ids
     *
     * @return int[]|null null when the request failed
     **/
    public function getUpdatedShowIDs(): ?array
    {
        $response = $this->request($this->baseUri . '/updates/shows?since=day', __METHOD__);

        if (!$response) {
            return null;
        }

        $data = json_decode((string)$response->getBody(), true);

        if (!is_array($data)) {
            $this->logError(__METHOD__, $response->getStatusCode(), 'Malformed updates payload');
            return null;
        }

        return array_keys($data);
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
        $response = $this->request($this->baseUri . '/shows/' . "$id", __METHOD__);

        if (!$response) {
            return null;
        }

        $data = json_decode((string)$response->getBody());

        if (!is_object($data) || !isset($data->id, $data->name, $data->status, $data->weight)) {
            $this->logError(__METHOD__, $response->getStatusCode(), 'Malformed show payload');
            return null;
        }

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
     * @return EpisodeData[]|null null when the request failed
     **/
    public function getEpisodes(int $showId): ?array
    {
        $response = $this->request(
            $this->baseUri . '/shows/' . "$showId" . '/episodes?specials=1',
            __METHOD__
        );

        if (!$response) {
            return null;
        }

        $episodes = json_decode((string)$response->getBody());

        if (!is_array($episodes)) {
            $this->logError(__METHOD__, $response->getStatusCode(), 'Malformed episodes payload');
            return null;
        }

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


    /**
     * Perform a GET request, retrying rate limits and server errors.
     *
     * Transport errors are already retried by the Guzzle middleware
     * (see client.retries), so they are not retried again here.
     **/
    private function request(string $url, string $method): ?ResponseInterface
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = $this->client->get($url, [
                    'http_errors' => false,
                    'headers' => ['User-Agent' => $this->userAgent],
                ]);
            } catch (GuzzleException $e) {
                $this->resetPause();
                error_log("$method transport error: " . $e->getMessage());
                return null;
            }

            $status = $response->getStatusCode();

            if ($status === 200) {
                $this->resetPause();
                return $response;
            }

            $retryable = $status === 429 || $status >= 500;

            if ($retryable && $attempt < self::MAX_ATTEMPTS) {
                $this->pauseAndIncrease();
                continue;
            }

            $this->logError($method, $status, $response->getReasonPhrase());
            $this->resetPause();
            return null;
        }

        return null;
    }

    private function logError(string $methodName, int $code, string $message): void
    {
        error_log("$methodName error with status code $code: " . $message);
    }

    private function pauseAndIncrease(): void
    {
        if ($this->retryDelaySeconds > 0) {
            sleep($this->pause * $this->retryDelaySeconds);
        }
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
}
