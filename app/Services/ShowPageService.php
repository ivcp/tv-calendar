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
    private const TITLE_MAX_LENGTH = 60;
    private const DESCRIPTION_MAX_LENGTH = 155;
    private const JSON_LD_DESCRIPTION_MAX_LENGTH = 500;

    public function __construct(private readonly Config $config)
    {
    }

    public function plainText(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        // Replace tags with a space: stripping them directly glues adjacent
        // blocks together ("</p><p>" would turn "one.</p><p>Two" into "one.Two").
        $separated = preg_replace('/<[^>]*>/', ' ', $html) ?? $html;
        $text = html_entity_decode($separated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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

    /**
     * @param EpisodeInfoData[] $episodes
     * @param array<int, array{number: int, is_specials: bool}> $seasons
     *
     * @return array{title: string, description: string}
     */
    public function seo(
        Show $show,
        string $summary,
        array $episodes,
        array $seasons
    ): array {
        $name = $show->getName();
        $nameWithYears = trim($name . ' ' . $this->years($show));

        $regularSeasons = count(array_filter(
            $seasons,
            fn(array $season): bool => ! $season['is_specials']
        ));
        $regularEpisodes = count(array_filter(
            $episodes,
            fn(EpisodeInfoData $episode): bool => $episode->type === 'regular'
        ));

        return [
            'title' => $this->title($name, $nameWithYears),
            'description' => $this->description(
                $show,
                $nameWithYears,
                $regularSeasons > 0 ? $regularSeasons : count($seasons),
                $regularEpisodes > 0 ? $regularEpisodes : count($episodes)
            ),
        ];
    }

    /**
     * @param array<int, array{number: int, is_specials: bool}> $seasons
     */
    public function jsonLd(
        Show $show,
        string $slug,
        string $summary,
        array $seasons,
        int $episodeCount
    ): string {
        $baseUrl = rtrim((string) $this->config->get('app_url'), '/');
        $url = "{$baseUrl}/shows/{$show->getId()}-{$slug}";

        $regularSeasons = count(array_filter(
            $seasons,
            fn(array $season): bool => ! $season['is_specials']
        ));

        $series = array_filter([
            '@type' => 'TVSeries',
            '@id' => $url . '#tvseries',
            'name' => $show->getName(),
            'description' => $this->truncate($summary, self::JSON_LD_DESCRIPTION_MAX_LENGTH),
            'image' => $show->getImageOriginal() ?? $show->getImageMedium(),
            'url' => $url,
            'genre' => $show->getGenres(),
            'datePublished' => $show->getPremiered(),
            'numberOfSeasons' => $regularSeasons > 0 ? $regularSeasons : null,
            'numberOfEpisodes' => $episodeCount > 0 ? $episodeCount : null,
            'sameAs' => $this->sameAs($show),
        ], fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        return json_encode(
            ['@context' => 'https://schema.org'] + $series,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_THROW_ON_ERROR
        );
    }

    private function title(string $name, string $nameWithYears): string
    {
        $name = trim($name);
        $nameWithYears = trim($nameWithYears);

        $candidates = [
            "{$nameWithYears} Episode Guide, Seasons & Air Dates |",
            "{$nameWithYears} Episode Guide |",
            "{$nameWithYears} |",
            "{$name} |",
        ];

        $brand = (string) $this->config->get('app_name', '');

        foreach ($candidates as $candidate) {
            $rendered = trim(trim($candidate) . ' ' . $brand);

            if (mb_strlen($rendered) <= self::TITLE_MAX_LENGTH) {
                return $candidate;
            }
        }

        return "{$name} |";
    }

    private function description(
        Show $show,
        string $nameWithYears,
        int $seasonCount,
        int $episodeCount
    ): string {
        $network = $show->getNetworkName() ?: $show->getWebChannelName();

        $parts = [
            sprintf(
                '%s episode guide: %d %s, %d %s%s.',
                $nameWithYears,
                $seasonCount,
                $seasonCount === 1 ? 'season' : 'seasons',
                $episodeCount,
                $episodeCount === 1 ? 'episode' : 'episodes',
                $network ? " on {$network}" : ''
            ),
        ];

        $status = $this->endedDescription($show);

        if ($status !== null) {
            $parts[] = $status;
        }

        $parts[] = 'Track it and get notified when a new episode airs.';

        while (count($parts) > 1
            && mb_strlen(implode(' ', $parts)) > self::DESCRIPTION_MAX_LENGTH
        ) {
            array_pop($parts);
        }

        return implode(' ', $parts);
    }

    private function endedDescription(Show $show): ?string
    {
        $year = $this->year($show->getEnded());

        return $year === null ? null : "Ended in {$year}.";
    }

    /**
     * @return string[]
     */
    private function sameAs(Show $show): array
    {
        $links = [];

        if ($show->getImdbId() !== null) {
            $links[] = 'https://www.imdb.com/title/' . $show->getImdbId();
        }

        if ($show->getTvMazeId() > 0) {
            $links[] = 'https://www.tvmaze.com/shows/' . $show->getTvMazeId();
        }

        if ($show->getOfficialSite() !== null) {
            $links[] = $show->getOfficialSite();
        }

        return $links;
    }

    private function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
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
