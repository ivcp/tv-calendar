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
        public readonly ?int $seasonNumber,
        public readonly ?int $episodeNumber,
        public readonly ?string $episodeSummary,
        public readonly ?string $type,
        public readonly ?DateTime $airstamp,
        public readonly ?int $runtime,
        public readonly ?string $imageMedium,
        public readonly ?string $imageOriginal,
    ) {}
}
