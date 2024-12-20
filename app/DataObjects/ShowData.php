<?php

declare(strict_types=1);

namespace App\DataObjects;

class ShowData
{
    public function __construct(
        public readonly int $tvMazeId,
        public readonly ?string $imdbId,
        public readonly ?array $genres,
        public readonly string $status,
        public readonly ?string $premiered,
        public readonly ?string $ended,
        public readonly ?string $officialSite,
        public readonly int $weight,
        public readonly ?string $networkName,
        public readonly ?string $networkCountry,
        public readonly ?string $webChannelName,
        public readonly ?string $webChannelCountry,
        public readonly ?string $summary,
        public readonly string $name,
        public readonly ?int $runtime,
        public readonly ?string $imageMedium,
        public readonly ?string $imageOriginal,
    ) {}
}
