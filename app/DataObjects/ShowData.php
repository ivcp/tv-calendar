<?php

declare(strict_types=1);

namespace App\DataObjects;

class ShowData
{
    public function __construct(
        public readonly int $tvMazeId,
        public readonly string $status,
        public readonly int $weight,
        public readonly string $name,
        public readonly ?string $imdbId = null,
        public readonly ?array $genres = null,
        public readonly ?string $premiered = null,
        public readonly ?string $ended = null,
        public readonly ?string $officialSite = null,
        public readonly ?string $networkName = null,
        public readonly ?string $networkCountry = null,
        public readonly ?string $webChannelName = null,
        public readonly ?string $webChannelCountry = null,
        public readonly ?string $summary = null,
        public readonly ?int $runtime = null,
        public readonly ?string $imageMedium = null,
        public readonly ?string $imageOriginal = null,
    ) {
    }
}
