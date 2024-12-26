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
        echo 'MEMORY BEFORE ' .   memory_get_usage() . PHP_EOL;

        $updatedShowIDs = $this->tvMazeService->getUpdatedShowIDs();
        if (!$updatedShowIDs) {
            //log
            return;
        }

        //getallshows
        $updatedShowsData = $this->tvMazeService->getShows($updatedShowIDs);


        $showsInDB = $this->showService->getShowsByTvMazeId($updatedShowIDs);

        $showsInDBIds = $showsInDB ? array_map(fn($show) => $show->getTvMazeId(), $showsInDB) : [];

        $showsToInsert = array_filter($updatedShowsData, fn($show) => !in_array($show->tvMazeId, $showsInDBIds));

        try {
            $insertedShows = $this->showService->insertShows($showsToInsert);
        } catch (\Throwable $e) {
            //log
            //abort
            return;
        }

        $epInsertCount = 0;

        foreach ($showsToInsert as $show) {
            $episodes = $this->tvMazeService->getEpisodes($show->tvMazeId);
            //bulk insert $episodes
            //tryc
            $insertedEpisodes = $this->episodeService->insertEpisodes($episodes);
            $epInsertCount += $insertedEpisodes;
        }

        echo 'SHOWS INSERTED: ' . $insertedShows . PHP_EOL;
        echo 'EPISODES INSERTED: ' . $epInsertCount . PHP_EOL;

        echo 'MEMORY AFTER ' .   memory_get_usage() . PHP_EOL;


        //clear memory or unset episodes




    }
}
