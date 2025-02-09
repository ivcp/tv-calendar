<?php

declare(strict_types=1);

namespace App\DataObjects;

class ScheduleData
{
    public function __construct(
        public readonly int $id,
        public readonly int $showId,
        public readonly string $showName,
        public readonly string $episodeName,
        public readonly int $seasonNumber,
        public readonly ?int $episodeNumber,
        public readonly ?string $episodeSummary,
        public readonly ?string $type,
        public readonly ?string $airstamp,
        public readonly ?string $image,
        public readonly ?string $networkName,
        public readonly ?string $webChannelName,
    ) {
    }
}
