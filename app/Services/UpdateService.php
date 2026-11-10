<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\DataObjects\ShowData;
use DateTime;
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Exception\GuzzleException;

class UpdateService
{
    public function __construct(
        private readonly ShowService $showService,
        private readonly EpisodeService $episodeService,
        private readonly TvMazeService $tvMazeService,
        private readonly EntityManager $entityManager,
        private readonly WebhookService $webhookService,
        private readonly Config $config
    ) {}

    public function run(): array
    {
        $showInsertCount = 0;
        $showUpdatedCount = 0;
        $epInsertCount = 0;
        $epUpdatedCount = 0;
        $epRemovedCount = 0;
        $showsSkipped = 0;
        $errors = 0;

        $updatedShows = $this->tvMazeService->getUpdatedShows();

        if ($updatedShows === null) {
            $errors += 1;
            error_log('ERROR getUpdatedShows: request failed');
        }

        if (!$updatedShows) {
            $this->report(
                $showInsertCount,
                $epInsertCount,
                $showUpdatedCount,
                $epUpdatedCount,
                $epRemovedCount,
                $showsSkipped,
                $errors
            );

            return [
                $showInsertCount,
                $epInsertCount,
                $showUpdatedCount,
                $epUpdatedCount,
                $epRemovedCount
            ];
        }

        $showsInDB = $this->showService->getShowsByTvMazeId(array_keys($updatedShows));

        $showsInDBByTvMazeId = [];
        foreach ($showsInDB as $show) {
            $showsInDBByTvMazeId[$show->getTvMazeId()] = $show;
        }

        $idsToFetch = [];
        foreach ($updatedShows as $tvMazeId => $feedUpdatedAt) {
            $local = $showsInDBByTvMazeId[$tvMazeId] ?? null;

            if (
                $local === null
                || $local->getTvMazeUpdatedAt() === null
                || $feedUpdatedAt > $local->getTvMazeUpdatedAt()
            ) {
                $idsToFetch[] = $tvMazeId;
            }
        }

        $updatedShowsData = $idsToFetch ? $this->tvMazeService->getShows($idsToFetch) : [];
        $showsSkipped = count($updatedShows) - count($idsToFetch);

        $showsToInsert = [];
        $showsToUpdate = [];

        foreach ($updatedShowsData as $showData) {
            $local = $showsInDBByTvMazeId[$showData->tvMazeId] ?? null;

            if ($local === null) {
                $showsToInsert[] = $showData;
            } else {
                $showsToUpdate[$local->getId()] = $showData;
            }
        }

        if ($showsToInsert) {
            $this->insertShowsAndEpisodes(
                $showsToInsert,
                $updatedShows,
                $showInsertCount,
                $epInsertCount,
                $errors
            );
        }

        if ($showsToUpdate) {
            $this->updateShowsAndEpisodes(
                $showsToUpdate,
                $updatedShows,
                $epInsertCount,
                $showUpdatedCount,
                $epUpdatedCount,
                $epRemovedCount,
                $errors
            );
        }


        $connection = $this->entityManager->getConnection();

        try {
            $connection->executeStatement('ANALYZE shows');
        } catch (\Exception $e) {
            $errors += 1;
            error_log('Failed to analyze shows table: ' . $e->getMessage());
        }

        try {
            $connection->executeStatement('ANALYZE episodes');
        } catch (\Exception $e) {
            $errors += 1;
            error_log('Failed to analyze episodes table: ' . $e->getMessage());
        }

        $this->report(
            $showInsertCount,
            $epInsertCount,
            $showUpdatedCount,
            $epUpdatedCount,
            $epRemovedCount,
            $showsSkipped,
            $errors
        );

        return [
            $showInsertCount,
            $epInsertCount,
            $showUpdatedCount,
            $epUpdatedCount,
            $epRemovedCount
        ];
    }

    private function report(
        int $showInsertCount,
        int $epInsertCount,
        int $showUpdatedCount,
        int $epUpdatedCount,
        int $epRemovedCount,
        int $showsSkipped,
        int $errors
    ): void {
        $dateTime = (new DateTime())->format('Y-m-d H:i:s');

        echo <<<UPDATE
        --------------------------
        UPDATING STATS $dateTime
        SHOWS INSERTED: $showInsertCount 
        EPISODES INSERTED:  $epInsertCount 
        SHOWS UPDATED: $showUpdatedCount 
        SHOWS SKIPPED: $showsSkipped 
        EPISODES UPDATED: $epUpdatedCount 
        EPISODES REMOVED:  $epRemovedCount
        ERRORS: $errors
        --------------------------\n 
        UPDATE;

        if ($errors) {
            $this->webhookService->send(
                $this->config->get('webhook_url'),
                "🚨 Update Service: $errors error(s)" .
                    " occurred while updating. Check the logs."
            );
        }
    }

    private function insertShowsAndEpisodes(
        array $showsToInsert,
        array $feedTimestamps,
        int &$showInsertCount,
        int &$epInsertCount,
        int &$errors
    ): void {

        try {
            $insertedShows = $this->showService->insertShows($showsToInsert);
        } catch (\Throwable $e) {
            $errors += 1;
            error_log('ERROR insertShows: ' . $e->getMessage());
            return;
        }

        $showInsertCount += $insertedShows;

        $syncedShows = [];
        foreach ($showsToInsert as $show) {
            try {
                $episodes = $this->tvMazeService->getEpisodes($show->tvMazeId);
            } catch (GuzzleException $e) {
                $errors += 1;
                error_log('ERROR getEpisodes: ' . $e->getMessage());
                continue;
            }

            if ($episodes === null) {
                $errors += 1;
                error_log("ERROR getEpisodes for $show->tvMazeId: request failed, skipping episodes");
                continue;
            }

            try {
                $insertedEpisodes = $this->episodeService->insertEpisodes($episodes);
                $epInsertCount += $insertedEpisodes;
            } catch (\Throwable $e) {
                $errors += 1;
                error_log("ERROR insert episodes for $show->tvMazeId: " . $e->getMessage());
                continue;
            }

            $syncedShows[] = $show;
        }

        $this->episodeService->connectEpisodesWithShows();

        foreach ($syncedShows as $show) {
            $this->advanceUpdatedAt($show, $feedTimestamps, $errors);
        }
    }

    private function updateShowsAndEpisodes(
        array $showsToUpdate,
        array $feedTimestamps,
        int &$epInsertCount,
        int &$showUpdatedCount,
        int &$epUpdatedCount,
        int &$epRemovedCount,
        int &$errors
    ): void {


        try {
            $updatedShows = $this->showService->updateShows($showsToUpdate);
            $showUpdatedCount += $updatedShows;
        } catch (\Throwable $e) {
            $errors += 1;
            error_log('ERROR update shows: ' . $e->getMessage());
            return;
        }


        $syncedShows = [];
        foreach ($showsToUpdate as $showId => $show) {
            try {
                $episodes = $this->tvMazeService->getEpisodes($show->tvMazeId);
            } catch (GuzzleException $e) {
                $errors += 1;
                error_log('ERROR getEpisodes: ' . $e->getMessage());
                continue;
            }

            if ($episodes === null) {
                $errors += 1;
                error_log("ERROR getEpisodes for $show->tvMazeId: request failed, skipping show");
                continue;
            }

            $episodesInDb = $this->showService->getById($showId)->getEpisodes();

            if ($episodes === [] && !$episodesInDb->isEmpty()) {
                $errors += 1;
                error_log(
                    "ERROR getEpisodes for $show->tvMazeId: empty list returned while " .
                        $episodesInDb->count() . " episodes exist in DB, skipping show"
                );
                continue;
            }

            $episodesToUpdate = [];
            $episodesInDbTvMazeIds = array_map(fn($e) => $e->getTvMazeEpisodeId(), $episodesInDb->toArray());
            $episodesInDbIds =  array_map(fn($e) => $e->getId(), $episodesInDb->toArray());
            foreach ($episodes as $episode) {
                $ep = $episodesInDb->findFirst(fn($k, $v) => $v->getTvMazeEpisodeId() === $episode->tvMazeEpisodeId);
                if ($ep) {
                    $episodesToUpdate[$ep->getId()] = $episode;
                }
            }

            $this->entityManager->clear();
            if ($episodesToUpdate) {
                $epsToUpdateFiltered = array_filter(
                    $episodesToUpdate,
                    fn($e) => $e->airstamp > new DateTime('7 days ago')
                );
                try {
                    $updatedEpisodesNumber = $this->episodeService->updateEpisodes($epsToUpdateFiltered, $showId);
                    $epUpdatedCount += $updatedEpisodesNumber;
                } catch (\Throwable $e) {
                    $errors += 1;
                    error_log("ERROR updateEpisodes for $show->tvMazeId: " . $e->getMessage());
                    continue;
                }
            }
            $episodesToInsert = array_filter(
                $episodes,
                fn($ep) => !in_array($ep->tvMazeEpisodeId, $episodesInDbTvMazeIds)
            );

            if ($episodesToInsert) {
                try {
                    $insertedEpisodes = $this->episodeService->insertEpisodes($episodesToInsert);
                    $epInsertCount += $insertedEpisodes;
                } catch (\Throwable $e) {
                    $errors += 1;
                    error_log("ERROR update insertEpisodes for $show->tvMazeId: " . $e->getMessage());
                    continue;
                }
            }

            $episodesToRemove = array_filter($episodesInDbIds, fn($e) => !in_array($e, array_keys($episodesToUpdate)));
            if ($episodesToRemove) {
                try {
                    $removedEpisodes = $this->episodeService->removeEpisodes($episodesToRemove);
                    $epRemovedCount += $removedEpisodes;
                } catch (\Throwable $e) {
                    $errors += 1;
                    error_log("ERROR update removeEpisodes: $show->tvMazeId: " . $e->getMessage());
                    continue;
                }
            }

            $syncedShows[] = $show;
        }

        $this->episodeService->connectEpisodesWithShows();

        foreach ($syncedShows as $show) {
            $this->advanceUpdatedAt($show, $feedTimestamps, $errors);
        }
    }

    /**
     * Timestamp of the payload that was actually received. Falls back to the
     * updates feed value when the show payload does not carry one.
     **/
    private function resolveUpdatedAt(ShowData $show, array $feedTimestamps): ?int
    {
        return $show->updated ?? $feedTimestamps[$show->tvMazeId] ?? null;
    }

    /**
     * Record a show as synced. Only called after every operation for that show
     * succeeded, so failures are retried on the next run.
     **/
    private function advanceUpdatedAt(ShowData $show, array $feedTimestamps, int &$errors): void
    {
        $updatedAt = $this->resolveUpdatedAt($show, $feedTimestamps);

        if ($updatedAt === null) {
            return;
        }

        try {
            $this->showService->updateTvMazeUpdatedAt($show->tvMazeId, $updatedAt);
        } catch (\Throwable $e) {
            $errors += 1;
            error_log("ERROR update tv_maze_updated_at for $show->tvMazeId: " . $e->getMessage());
        }
    }
}
