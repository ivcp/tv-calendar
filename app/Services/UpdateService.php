<?php

declare(strict_types=1);

namespace App\Services;

use DateTime;
use Doctrine\ORM\EntityManager;

class UpdateService
{
    public function __construct(
        private readonly ShowService $showService,
        private readonly EpisodeService $episodeService,
        private readonly TvMazeService $tvMazeService,
        private readonly EntityManager $entityManager
    ) {
    }

    public function run(): array
    {
        $showInsertCount = 0;
        $showUpdatedCount = 0;
        $epInsertCount = 0;
        $epUpdatedCount = 0;
        $epRemovedCount = 0;

        $updatedShowIDs = $this->tvMazeService->getUpdatedShowIDs();
        if (!$updatedShowIDs) {
            return [
                $showInsertCount,
                $epInsertCount,
                $showUpdatedCount,
                $epUpdatedCount,
                $epRemovedCount
            ];
        }

        $updatedShowsData = $this->tvMazeService->getShows($updatedShowIDs);

        $showsInDB = $this->showService->getShowsByTvMazeId($updatedShowIDs);

        $showsInDBIds = $showsInDB ? array_map(fn($show) => $show->getTvMazeId(), $showsInDB) : [];

        $showsToInsert = array_values(
            array_filter($updatedShowsData, fn($show) => !in_array($show->tvMazeId, $showsInDBIds))
        );




        if ($showsToInsert) {
            $this->insertShowsAndEpisodes($showsToInsert, $showInsertCount, $epInsertCount);
        }

        $showsToUpdate = [];

        foreach ($showsInDB as $show) {
            $showData = null;
            foreach ($updatedShowsData as $updatedData) {
                if ($updatedData->tvMazeId === $show->getTvMazeId()) {
                    $showData = $updatedData;
                }
            }
            if ($showData) {
                $showsToUpdate[$show->getId()] = $showData;
            }
        }

        if ($showsToUpdate) {
            $this->updateShowsAndEpisodes(
                $showsToUpdate,
                $epInsertCount,
                $showUpdatedCount,
                $epUpdatedCount,
                $epRemovedCount
            );
        }


        echo "
        SHOWS INSERTED: $showInsertCount \n
        EPISODES INSERTED:  $epInsertCount \n
        SHOWS UPDATED: $showUpdatedCount \n
        EPISODES UPDATED: $epUpdatedCount \n
        EPISODES REMOVED:  $epRemovedCount \n";


        return [
            $showInsertCount,
            $epInsertCount,
            $showUpdatedCount,
            $epUpdatedCount,
            $epRemovedCount
        ];
    }

    private function insertShowsAndEpisodes(array $showsToInsert, int &$showInsertCount, int &$epInsertCount): void
    {
        try {
            $insertedShows = $this->showService->insertShows($showsToInsert);
        } catch (\Throwable $e) {
            //log
            //abort
            error_log('ERROR insertShows: ' . $e->getMessage());
            return;
        }

        $showInsertCount += $insertedShows;

        foreach ($showsToInsert as $show) {
            $episodes = $this->tvMazeService->getEpisodes($show->tvMazeId);

            try {
                $insertedEpisodes = $this->episodeService->insertEpisodes($episodes);
                $epInsertCount += $insertedEpisodes;
            } catch (\Throwable $e) {
                //log it
                error_log("ERROR insert episodes for $show->tvMazeId: " . $e->getMessage());
            }
        }

        $this->episodeService->connectEpisodesWithShows();
    }

    private function updateShowsAndEpisodes(
        array $showsToUpdate,
        int &$epInsertCount,
        int &$showUpdatedCount,
        int &$epUpdatedCount,
        int &$epRemovedCount
    ): void {


        try {
            $updatedShows = $this->showService->updateShows($showsToUpdate);
            $showUpdatedCount += $updatedShows;
        } catch (\Throwable $e) {
            //log
            error_log('ERROR update shows: ' . $e->getMessage());
            return;
        }


        foreach ($showsToUpdate as $showId => $show) {
            $episodes = $this->tvMazeService->getEpisodes($show->tvMazeId);

            $episodesInDb = $this->showService->getById($showId)->getEpisodes();

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
                    //log
                    error_log("ERROR updateEpisodes for $show->tvMazeId: " . $e->getMessage());
                    return;
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
                    $this->episodeService->connectEpisodesWithShows();
                } catch (\Throwable $e) {
                    error_log("ERROR update insertEpisodes for $show->tvMazeId: " . $e->getMessage());
                    return;
                }
            }

            $episodesToRemove = array_filter($episodesInDbIds, fn($e) => !in_array($e, array_keys($episodesToUpdate)));
            if ($episodesToRemove) {
                try {
                    $removedEpisodes = $this->episodeService->removeEpisodes($episodesToRemove);
                    $epRemovedCount += $removedEpisodes;
                } catch (\Throwable $e) {
                    error_log("ERROR update removeEpisodes: $show->tvMazeId: " . $e->getMessage());
                    return;
                }
            }
        }
    }
}
