<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Episode;
use App\Entity\Show;
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

        //getallshows
        $updatedShowsData = $this->tvMazeService->getShows($updatedShowIDs);

        $showsInDB = $this->showService->getShowsByTvMazeId($updatedShowIDs);

        $showsInDBIds = $showsInDB ? array_map(fn($show) => $show->getTvMazeId(), $showsInDB) : [];

        $showsToInsert = array_values(
            array_filter($updatedShowsData, fn($show) => !in_array($show->tvMazeId, $showsInDBIds))
        );


        if ($showsToInsert) {
            $this->insertShowsAndEpisodes($showsToInsert);
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
            try {
                $updatedShows = $this->showService->updateShows($showsToUpdate);
            } catch (\Throwable $e) {
                //log
                echo 'ERROR update shows: ' . $e->getMessage() . PHP_EOL;
                return;
            }

            $epUpdatedCount = 0;

            foreach ($showsToUpdate as $showId => $show) {
                $episodes = $this->tvMazeService->getEpisodes($show->tvMazeId);
                $episodesInDb = $this->showService->getById($showId)->getEpisodes();

                $episodesToUpdate = [];
                $episodesInDbTvMazeIds = [];
                foreach ($episodes as $episode) {
                    //check which are updated 
                    if ($episode->tvMazeEpisodeId === $episodesInDb->current()->getTvMazeEpisodeId()) {
                        $episodesToUpdate[$episodesInDb->current()->getId()] = $episode;
                    }
                    $episodesInDbTvMazeIds[] = $episodesInDb->current()->getTvMazeEpisodeId();
                    $episodesInDb->next();
                    //check if episodes added
                    //check if eps removed
                }

                $updatedEpisodesNumber = $this->episodeService->updateEpisodes($episodesToUpdate, $showId);
                $epUpdatedCount += $updatedEpisodesNumber;


                $episodesToInsert = array_filter(
                    $episodes,
                    fn($ep) => !in_array($ep->tvMazeEpisodeId, $episodesInDbTvMazeIds)
                );
                //var_dump($episodesToInsert);

                //var_dump($episodesToUpdate);
                //insert eps 
            }

            echo 'UNIT OF WORK ' . $this->entityManager->getUnitOfWork()->size() . PHP_EOL;
            echo 'UPDATED SHOWS: ' . $updatedShows . PHP_EOL;
            echo 'UPDATED EPISODES: ' . $epUpdatedCount . PHP_EOL;
        }






        //TODO:
        //update existing shows
        //update eps


        echo 'UPDATE run() MEMORY USAGE END ' . memory_get_usage() . PHP_EOL;


        //clear memory or unset episodes
        //implement logging

    }

    private function insertShowsAndEpisodes(array $showsToInsert): void
    {
        try {
            $insertedShows = $this->showService->insertShows($showsToInsert);
        } catch (\Throwable $e) {
            //log
            //abort
            echo 'ERROR insert shows: ' . $e->getMessage() . PHP_EOL;
            return;
        }

        $epInsertCount = 0;

        foreach ($showsToInsert as $show) {
            $episodes = $this->tvMazeService->getEpisodes($show->tvMazeId);

            try {
                $insertedEpisodes = $this->episodeService->insertEpisodes($episodes);
                $epInsertCount += $insertedEpisodes;
            } catch (\Throwable $e) {
                //log it
                echo 'ERROR insert episodes: ' . $e->getMessage() . PHP_EOL;
            }
        }

        $connected = $this->episodeService->connectEpisodesWithShows();

        echo 'SHOWS INSERTED: ' . $insertedShows . PHP_EOL;
        echo 'EPISODES INSERTED: ' . $epInsertCount . PHP_EOL;
        echo 'EPISODES CONNECTED: ' . $connected . PHP_EOL;
    }
}
