<?php

declare(strict_types=1);

namespace App\DataObjects;

class ScheduleData
{
    public function __construct(
        public readonly int $tvMazeEpisodeId,
        public readonly int $tvMazeShowId,
        public readonly string $episodeName,
        public readonly int $seasonNumber,
        public readonly ?int $episodeNumber,
        public readonly ?string $episodeSummary,
        public readonly string $airstamp,
        public readonly string $showName,
        public readonly int $weight,
    ) {}
}
