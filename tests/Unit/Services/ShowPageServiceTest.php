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

    private function service(): ShowPageService
    {
        return new ShowPageService(new Config(['app_url' => 'https://example.test']));
    }

    private function show(?string $premiered, ?string $ended = null): Show
    {
        $show = $this->createMock(Show::class);
        $show->method('getPremiered')->willReturn($premiered);
        $show->method('getEnded')->willReturn($ended);

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
