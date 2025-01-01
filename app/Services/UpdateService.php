<?php

declare(strict_types=1);

namespace App\Services;

use Doctrine\ORM\EntityManager;

class UpdateService
{
    public function __construct(
        private readonly ShowService $showService,
        private readonly EpisodeService $episodeService,
        private readonly TvMazeService $tvMazeService,
        private readonly EntityManager $entityManager
    ) {}

    public function run(): void
    {
        echo 'UPDATE run() MEMORY USAGE START ' . memory_get_usage() . PHP_EOL;

        $updatedShowIDs = $this->tvMazeService->getUpdatedShowIDs();
        if (!$updatedShowIDs) {
            //log
            echo 'NO SHOWS' . PHP_EOL;
            return;
        }


        $updatedShowsData = $this->tvMazeService->getShows($updatedShowIDs);

        $showsInDB = $this->showService->getShowsByTvMazeId($updatedShowIDs);

        $showsInDBIds = $showsInDB ? array_map(fn($show) => $show->getTvMazeId(), $showsInDB) : [];

        $showsToInsert = array_values(
            array_filter($updatedShowsData, fn($show) => !in_array($show->tvMazeId, $showsInDBIds))
        );

        $showInsertCount = 0;
        $showUpdatedCount = 0;
        $epInsertCount = 0;
        $epUpdatedCount = 0;
        $epRemovedCount = 0;


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

        echo 'SHOWS INSERTED: ' . $showInsertCount . PHP_EOL;
        echo 'EPISODES INSERTED: ' . $epInsertCount . PHP_EOL;
        echo 'SHOWS UPDATED: ' . $showUpdatedCount . PHP_EOL;
        echo 'EPISODES UPDATED: ' . $epUpdatedCount . PHP_EOL;
        echo 'EPISODES REMOVED: ' . $epRemovedCount . PHP_EOL;

        echo 'UPDATE run() MEMORY USAGE END ' . memory_get_usage() . PHP_EOL;


        //clear memory or unset episodes
        //implement logging

    }

    private function insertShowsAndEpisodes(array $showsToInsert, int &$showInsertCount, int &$epInsertCount): void
    {
        try {
            $insertedShows = $this->showService->insertShows($showsToInsert);
        } catch (\Throwable $e) {
            //log
            //abort
            echo 'ERROR insert shows: ' . $e->getMessage() . PHP_EOL;
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
                echo "ERROR insert episodes for $show->tvMazeId: " . $e->getMessage() . PHP_EOL;
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
            echo 'ERROR update shows: ' . $e->getMessage() . PHP_EOL;
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
                $episodesToUpdate[$ep->getId()] = $episode;
            }

            $this->entityManager->clear();
            if ($episodesToUpdate) {
                try {
                    $updatedEpisodesNumber = $this->episodeService->updateEpisodes($episodesToUpdate, $showId);
                    $epUpdatedCount += $updatedEpisodesNumber;
                } catch (\Throwable $e) {
                    //log
                    echo "ERROR updateEpisodes for $show->tvMazeId: " . $e->getMessage() . PHP_EOL;
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
                    echo "ERROR update insertEpisodesfor $show->tvMazeId: " . $e->getMessage() . PHP_EOL;
                    return;
                }
            }

            $episodesToRemove = array_filter($episodesInDbIds, fn($e) => !in_array($e,  array_keys($episodesToUpdate)));

            if ($episodesToRemove) {
                try {
                    $removedEpisodes = $this->episodeService->removeEpisodes($episodesToRemove);
                    $epRemovedCount += $removedEpisodes;
                } catch (\Throwable $e) {
                    echo "ERROR update removeEpisodes: $show->tvMazeId: " . $e->getMessage() . PHP_EOL;
                    return;
                }
            }
        }

        //echo 'UNIT OF WORK ' . $this->entityManager->getUnitOfWork()->size() . PHP_EOL;
    }
}
