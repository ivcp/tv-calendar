<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Config;
use App\DataObjects\EpisodeInfoData;
use App\Entity\Show;
use App\Services\ShowPageService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ShowPageServiceTest extends TestCase
{
    public function testItStripsTagsAndDecodesEntities(): void
    {
        $this->assertSame(
            "Rock & Roll 'n' roll",
            $this->service()->plainText('<p>Rock &amp; Roll &#39;n&#39; roll</p>')
        );
    }

    public function testItRemovesInlineTags(): void
    {
        $this->assertSame('A B', $this->service()->plainText('A <em>B</em>'));
    }

    public function testItKeepsWordsSeparatedWhenBlockTagsAreAdjacent(): void
    {
        $this->assertSame(
            'First paragraph. Second paragraph.',
            $this->service()->plainText('<p>First paragraph.</p><p>Second paragraph.</p>')
        );
    }

    public function testItKeepsWordsSeparatedAroundLineBreaks(): void
    {
        $this->assertSame('A B', $this->service()->plainText('A<br>B'));
    }

    public function testItCollapsesWhitespace(): void
    {
        $this->assertSame('A B', $this->service()->plainText("  A\n\t B  "));
    }

    public function testItHandlesANullSummary(): void
    {
        $this->assertSame('', $this->service()->plainText(null));
    }

    public function testItShowsASingleYearForARunningShow(): void
    {
        $this->assertSame('(2019)', $this->service()->years($this->show('2019-09-01')));
    }

    public function testItShowsARangeForAnEndedShow(): void
    {
        $this->assertSame('(2019–2023)', $this->service()->years($this->show('2019-09-01', '2023-05-14')));
    }

    public function testItCollapsesASingleYear(): void
    {
        $this->assertSame('(2024)', $this->service()->years($this->show('2024-02-01', '2024-12-01')));
    }

    public function testItOmitsYearsWithoutAPremiere(): void
    {
        $this->assertSame('', $this->service()->years($this->show(null)));
    }

    public function testItFindsTheNextAndLastEpisode(): void
    {
        $now = new DateTimeImmutable('2026-11-18T12:00:00+00:00');
        $episodes = [
            $this->episode(1, airstamp: '2026-11-10T20:00:00+00:00'),
            $this->episode(2),
            $this->episode(3, airstamp: '2026-11-25T20:00:00+00:00'),
            $this->episode(4, airstamp: '2026-11-20T20:00:00+00:00'),
        ];

        $result = $this->service()->nextAndLast($episodes, $now);

        $this->assertSame(4, $result['next']->id);
        $this->assertSame(1, $result['last']->id);
    }

    public function testItTreatsAnEpisodeAiringNowAsLast(): void
    {
        $now = new DateTimeImmutable('2026-11-18T12:00:00+00:00');
        $episodes = [$this->episode(1, airstamp: '2026-11-18T12:00:00+00:00')];

        $result = $this->service()->nextAndLast($episodes, $now);

        $this->assertNull($result['next']);
        $this->assertSame(1, $result['last']->id);
    }

    public function testItReturnsNoEpisodesForAnEmptyList(): void
    {
        $result = $this->service()->nextAndLast([], new DateTimeImmutable());

        $this->assertNull($result['next']);
        $this->assertNull($result['last']);
    }

    public function testItGroupsEpisodesBySeasonAndSkipsGaps(): void
    {
        $seasons = $this->service()->seasons([
            $this->episode(1, season: 1, airstamp: '2019-01-01T20:00:00+00:00'),
            $this->episode(2, season: 1, airstamp: '2019-02-01T20:00:00+00:00'),
            $this->episode(3, season: 1, type: 'special', airstamp: '2019-03-01T20:00:00+00:00'),
            $this->episode(4, season: 2, airstamp: '2020-01-01T20:00:00+00:00'),
            $this->episode(5, season: 4, airstamp: '2022-01-01T20:00:00+00:00'),
            $this->episode(6, season: 0, type: 'special', airstamp: '2018-12-01T20:00:00+00:00'),
        ]);

        $this->assertSame([4, 2, 1, 0], array_column($seasons, 'number'));

        $seasonOne = $seasons[2];
        $this->assertSame(2, $seasonOne['episodes']);
        $this->assertSame(1, $seasonOne['specials']);
        $this->assertSame('2019-01-01T20:00:00+00:00', $seasonOne['from']);
        $this->assertSame('2019-03-01T20:00:00+00:00', $seasonOne['to']);
        $this->assertFalse($seasonOne['is_specials']);
        $this->assertSame('Season 1', $seasonOne['label']);

        $this->assertTrue($seasons[3]['is_specials']);
        $this->assertSame('Other', $seasons[3]['label']);
    }

    public function testItHandlesAShowWithOnlySpecials(): void
    {
        $seasons = $this->service()->seasons([
            $this->episode(1, season: 0, type: 'special'),
            $this->episode(2, season: 0, type: 'special'),
        ]);

        $this->assertCount(1, $seasons);
        $this->assertSame(0, $seasons[0]['number']);
        $this->assertSame(0, $seasons[0]['episodes']);
        $this->assertSame(2, $seasons[0]['specials']);
    }

    public function testItPrefersTheNextEpisodeSeason(): void
    {
        $seasons = $this->service()->seasons([$this->episode(1, season: 3)]);

        $this->assertSame(
            3,
            $this->service()->currentSeason($seasons, $this->episode(2, season: 3), null)
        );
    }

    public function testItFallsBackToTheLastEpisodeSeason(): void
    {
        $seasons = $this->service()->seasons([$this->episode(1, season: 2)]);

        $this->assertSame(
            2,
            $this->service()->currentSeason($seasons, null, $this->episode(1, season: 2))
        );
    }

    public function testItDoesNotOpenSpecialsWhenRegularEpisodesExist(): void
    {
        $seasons = $this->service()->seasons([
            $this->episode(1, season: 2, airstamp: '2020-01-01T20:00:00+00:00'),
            $this->episode(2, season: 0, type: 'special'),
        ]);

        $this->assertSame(
            2,
            $this->service()->currentSeason($seasons, $this->episode(2, season: 0), $this->episode(1, season: 2))
        );
    }

    public function testItFallsBackToTheHighestRegularSeason(): void
    {
        $seasons = $this->service()->seasons([
            $this->episode(1, season: 1),
            $this->episode(2, season: 4),
            $this->episode(3, season: 0, type: 'special'),
        ]);

        $this->assertSame(4, $this->service()->currentSeason($seasons, null, null));
    }

    public function testItOpensSpecialsWhenTheyAreTheOnlyContent(): void
    {
        $seasons = $this->service()->seasons([$this->episode(1, season: 0, type: 'special')]);

        $this->assertSame(0, $this->service()->currentSeason($seasons, null, null));
    }

    public function testItUsesTheFullSuffixWhenTheSeoTitleFits(): void
    {
        $seo = $this->service()->seo($this->show(null, null, 'Lost'), '', [], []);

        $this->assertSame('Lost Episode Guide, Seasons & Air Dates |', $seo['title']);
    }

    public function testItShortensTheSeoTitleForMediumShowNames(): void
    {
        $seo = $this->service()->seo(
            $this->show('2008-01-20', null, 'Breaking Bad'),
            '',
            [],
            []
        );

        $this->assertSame('Breaking Bad (2008) Episode Guide |', $seo['title']);
    }

    public function testItDropsTheYearsForLongShowNames(): void
    {
        $seo = $this->service()->seo(
            $this->show('2022-09-01', null, 'The Lord of the Rings: The Rings of Power'),
            '',
            [],
            []
        );

        $this->assertSame('The Lord of the Rings: The Rings of Power |', $seo['title']);
    }

    public function testItNeverTruncatesTheShowNameInTheSeoTitle(): void
    {
        $name = str_repeat('An Extremely Long Show Name ', 3);
        $seo = $this->service()->seo($this->show('2022-09-01', null, $name), '', [], []);

        $this->assertSame(trim($name) . ' |', $seo['title']);
    }

    public function testItDescribesARunningShowWithStableDataOnly(): void
    {
        $episodes = [
            $this->episode(1, season: 1, airstamp: '2019-09-24T20:00:00+00:00'),
            $this->episode(2, season: 1, airstamp: '2019-10-01T20:00:00+00:00'),
        ];
        $seasons = $this->service()->seasons($episodes);

        $seo = $this->service()->seo(
            $this->show('2019-09-01', null, 'Test Show'),
            '',
            $episodes,
            $seasons
        );

        $this->assertStringStartsWith(
            'Test Show (2019) episode guide: 1 season, 2 episodes on HBO.',
            $seo['description']
        );
        $this->assertStringContainsString(
            'Track it and get notified when a new episode airs.',
            $seo['description']
        );
        $this->assertStringNotContainsString('Next episode', $seo['description']);
    }

    public function testItDescribesAnEndedShow(): void
    {
        $episodes = [$this->episode(1, season: 1, airstamp: '2023-05-14T20:00:00+00:00')];

        $seo = $this->service()->seo(
            $this->show('2019-09-01', '2023-05-14', 'Test Show'),
            '',
            $episodes,
            $this->service()->seasons($episodes)
        );

        $this->assertStringContainsString('Ended in 2023.', $seo['description']);
    }

    public function testItOmitsTheNetworkWhenNoneIsKnown(): void
    {
        $seo = $this->service()->seo(
            $this->show('2019-09-01', null, 'Test Show', null, null),
            '',
            [],
            []
        );

        $this->assertStringStartsWith(
            'Test Show (2019) episode guide: 0 seasons, 0 episodes.',
            $seo['description']
        );
        $this->assertStringNotContainsString(' on ', $seo['description']);
    }

    public function testItDropsDescriptionClausesThatWouldExceedTheLimit(): void
    {
        $name = str_repeat('Long Show Name ', 5);
        $episodes = [$this->episode(1, season: 1, airstamp: '2023-05-14T20:00:00+00:00')];

        $seo = $this->service()->seo(
            $this->show('2019-09-01', '2023-05-14', $name),
            '',
            $episodes,
            $this->service()->seasons($episodes)
        );

        $this->assertLessThanOrEqual(155, mb_strlen($seo['description']));
        $this->assertStringContainsString('Ended in 2023.', $seo['description']);
        $this->assertStringNotContainsString('Track it', $seo['description']);
    }

    public function testItBuildsTvSeriesStructuredData(): void
    {
        $episodes = [
            $this->episode(1, season: 1, airstamp: '2019-09-24T20:00:00+00:00'),
            $this->episode(2, season: 2, airstamp: '2020-09-24T20:00:00+00:00'),
            $this->episode(3, season: 2, airstamp: '2020-10-01T20:00:00+00:00'),
            $this->episode(4, season: 0, type: 'special'),
        ];

        $json = $this->service()->jsonLd(
            $this->structuredShow(),
            'test-show',
            'A great show.',
            $this->service()->seasons($episodes),
            12
        );
        $series = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org', $series['@context']);
        $this->assertSame('TVSeries', $series['@type']);
        $this->assertSame('https://example.test/shows/42-test-show#tvseries', $series['@id']);
        $this->assertSame('Test Show', $series['name']);
        $this->assertSame('A great show.', $series['description']);
        $this->assertSame('https://static.tvmaze.com/original.jpg', $series['image']);
        $this->assertSame('https://example.test/shows/42-test-show', $series['url']);
        $this->assertSame(['Drama', 'Crime'], $series['genre']);
        $this->assertSame('2019-09-01', $series['datePublished']);
        $this->assertSame(2, $series['numberOfSeasons']);
        $this->assertSame(12, $series['numberOfEpisodes']);
        $this->assertSame([
            'https://www.imdb.com/title/tt1234567',
            'https://www.tvmaze.com/shows/1001',
            'https://example.com/test-show',
        ], $series['sameAs']);
        $this->assertArrayNotHasKey('@graph', $series);
    }

    public function testItOmitsMissingOptionalStructuredDataFields(): void
    {
        $show = $this->createMock(Show::class);
        $show->method('getId')->willReturn(7);
        $show->method('getName')->willReturn('Bare Show');

        $series = json_decode(
            $this->service()->jsonLd($show, 'bare-show', '', [], 0),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertArrayNotHasKey('description', $series);
        $this->assertArrayNotHasKey('image', $series);
        $this->assertArrayNotHasKey('genre', $series);
        $this->assertArrayNotHasKey('datePublished', $series);
        $this->assertArrayNotHasKey('numberOfSeasons', $series);
        $this->assertArrayNotHasKey('numberOfEpisodes', $series);
        $this->assertArrayNotHasKey('sameAs', $series);
    }

    public function testItEscapesScriptTagsInTheStructuredData(): void
    {
        $show = $this->structuredShow('<script>alert("x")</script>');

        $json = $this->service()->jsonLd($show, 'x', '', [], 0);

        $this->assertStringNotContainsString('<script>', $json);
        $this->assertStringContainsString('\u003Cscript\u003E', $json);
    }

    private function service(): ShowPageService
    {
        return new ShowPageService(new Config([
            'app_url' => 'https://example.test',
            'app_name' => 'TV Show Calendar',
        ]));
    }

    private function show(
        ?string $premiered,
        ?string $ended = null,
        string $name = 'Test Show',
        ?string $network = 'HBO',
        ?string $webChannel = null
    ): Show {
        $show = $this->createMock(Show::class);
        $show->method('getName')->willReturn($name);
        $show->method('getPremiered')->willReturn($premiered);
        $show->method('getEnded')->willReturn($ended);
        $show->method('getNetworkName')->willReturn($network);
        $show->method('getWebChannelName')->willReturn($webChannel);

        return $show;
    }

    private function structuredShow(string $name = 'Test Show'): Show
    {
        $show = $this->createMock(Show::class);
        $show->method('getId')->willReturn(42);
        $show->method('getName')->willReturn($name);
        $show->method('getImageOriginal')->willReturn('https://static.tvmaze.com/original.jpg');
        $show->method('getImageMedium')->willReturn('https://static.tvmaze.com/medium.jpg');
        $show->method('getGenres')->willReturn(['Drama', 'Crime']);
        $show->method('getPremiered')->willReturn('2019-09-01');
        $show->method('getEnded')->willReturn('2023-05-14');
        $show->method('getImdbId')->willReturn('tt1234567');
        $show->method('getTvMazeId')->willReturn(1001);
        $show->method('getOfficialSite')->willReturn('https://example.com/test-show');
        $show->method('getNetworkName')->willReturn('HBO');
        $show->method('getWebChannelName')->willReturn(null);

        return $show;
    }

    private function episode(
        int $id,
        int $season = 1,
        ?string $type = 'regular',
        ?string $airstamp = null
    ): EpisodeInfoData {
        return new EpisodeInfoData(
            id: $id,
            showId: 1,
            showName: 'Test Show',
            showSlug: 'test-show',
            episodeName: 'Episode ' . $id,
            seasonNumber: $season,
            episodeNumber: $id,
            episodeSummary: null,
            type: $type,
            airstamp: $airstamp,
            image: null,
            networkName: null,
            webChannelName: null,
        );
    }
}
