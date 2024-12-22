<?php

declare(strict_types=1);

namespace App\Services;


class UpdateService
{
    public function __construct(
        private readonly ShowService $showService,
        private readonly EpisodeService $episodeService,
        private readonly TvMazeService $tvMazeService

    ) {}

    public function run(): void
    {
        $updatedShows = $this->tvMazeService->getUpdatedShows();
        if (!$updatedShows) {
            //abort
            return;
        }
        var_dump($updatedShows);
    }
}
