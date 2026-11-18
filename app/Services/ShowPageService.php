<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\DataObjects\EpisodeInfoData;
use App\Entity\Show;
use DateTimeImmutable;
use Throwable;

class ShowPageService
{
    public function __construct(private readonly Config $config)
    {
    }

    public function plainText(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $text);

        return trim($collapsed ?? $text);
    }

    public function years(Show $show): string
    {
        $premiered = $this->year($show->getPremiered());

        if ($premiered === null) {
            return '';
        }

        $ended = $this->year($show->getEnded());

        if ($ended === null || $ended === $premiered) {
            return "({$premiered})";
        }

        return "({$premiered}–{$ended})";
    }

    /**
     * @param EpisodeInfoData[] $episodes
     *
     * @return array{next: ?EpisodeInfoData, last: ?EpisodeInfoData}
     */
    public function nextAndLast(array $episodes, DateTimeImmutable $now): array
    {
        $next = null;
        $nextAirstamp = null;
        $last = null;
        $lastAirstamp = null;

        foreach ($episodes as $episode) {
            $airstamp = $this->airstamp($episode);

            if ($airstamp === null) {
                continue;
            }

            if ($airstamp > $now) {
                if ($nextAirstamp === null || $airstamp < $nextAirstamp) {
                    $next = $episode;
                    $nextAirstamp = $airstamp;
                }

                continue;
            }

            if ($lastAirstamp === null || $airstamp > $lastAirstamp) {
                $last = $episode;
                $lastAirstamp = $airstamp;
            }
        }

        return ['next' => $next, 'last' => $last];
    }

    /**
     * @param EpisodeInfoData[] $episodes
     *
     * @return array<int, array{
     *     number: int, label: string, episodes: int, specials: int,
     *     from: ?string, to: ?string, is_specials: bool
     * }>
     */
    public function seasons(array $episodes): array
    {
        $seasons = [];
        $ranges = [];

        foreach ($episodes as $episode) {
            $number = $episode->seasonNumber;

            if (! isset($seasons[$number])) {
                $seasons[$number] = [
                    'number' => $number,
                    'label' => $number === 0 ? 'Other' : "Season {$number}",
                    'episodes' => 0,
                    'specials' => 0,
                    'from' => null,
                    'to' => null,
                    'is_specials' => $number === 0,
                ];
            }

            if ($episode->type === 'regular') {
                $seasons[$number]['episodes']++;
            } else {
                $seasons[$number]['specials']++;
            }

            $airstamp = $this->airstamp($episode);

            if ($airstamp === null) {
                continue;
            }

            if (! isset($ranges[$number])) {
                $ranges[$number] = ['from' => $airstamp, 'to' => $airstamp];

                continue;
            }

            if ($airstamp < $ranges[$number]['from']) {
                $ranges[$number]['from'] = $airstamp;
            }

            if ($airstamp > $ranges[$number]['to']) {
                $ranges[$number]['to'] = $airstamp;
            }
        }

        foreach ($ranges as $number => $range) {
            $seasons[$number]['from'] = $range['from']->format(DATE_ATOM);
            $seasons[$number]['to'] = $range['to']->format(DATE_ATOM);
        }

        usort($seasons, function (array $a, array $b): int {
            if ($a['is_specials'] !== $b['is_specials']) {
                return $a['is_specials'] ? 1 : -1;
            }

            return $b['number'] <=> $a['number'];
        });

        return array_values($seasons);
    }

    /**
     * @param array<int, array{number: int, is_specials: bool}> $seasons
     */
    public function currentSeason(
        array $seasons,
        ?EpisodeInfoData $next,
        ?EpisodeInfoData $last
    ): int {
        if ($next !== null && ! $this->isSpecials($next)) {
            return $next->seasonNumber;
        }

        if ($last !== null && ! $this->isSpecials($last)) {
            return $last->seasonNumber;
        }

        $regularSeasons = array_filter(
            array_column($seasons, 'number'),
            fn(int $number): bool => $number > 0
        );

        if ($regularSeasons !== []) {
            return max($regularSeasons);
        }

        return 0;
    }

    private function isSpecials(EpisodeInfoData $episode): bool
    {
        return $episode->seasonNumber === 0;
    }

    private function airstamp(EpisodeInfoData $episode): ?DateTimeImmutable
    {
        if ($episode->airstamp === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($episode->airstamp);
        } catch (Throwable) {
            return null;
        }
    }

    private function year(?string $date): ?string
    {
        if ($date === null || preg_match('/^(\d{4})/', $date, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
