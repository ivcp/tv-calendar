<?php

declare(strict_types=1);

namespace App\DataObjects;

use DateTime;

class EpisodeData
{
    public function __construct(
        public readonly int $tvMazeShowId,
        public readonly int $tvMazeEpisodeId,
        public readonly string $episodeName,
        public readonly ?int $seasonNumber = null,
        public readonly ?int $episodeNumber = null,
        public readonly ?string $episodeSummary = null,
        public readonly ?string $type = null,
        public readonly ?DateTime $airstamp = null,
        public readonly ?int $runtime = null,
        public readonly ?string $imageMedium = null,
        public readonly ?string $imageOriginal = null,
    ) {
    }
}
