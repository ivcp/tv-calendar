<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Config;
use App\Entity\Episode;
use App\Entity\Show;
use App\Entity\User;
use App\Enum\NotificationTime;
use App\Enum\ScheduleType;
use App\Services\EpisodeService;
use DateTime;
use DI\Container;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

final class EpisodeServiceTest extends TestCase
{
    private Container $container;
    private EpisodeService $episodeService;
    private EntityManager $em;
    private Config $config;

    private Show $popularShow;
    private Show $unpopularShow;
    private Show $guestShow;
    private Show $highWeightShow;
    private Show $atThresholdShow;
    private Show $aboveThresholdShow;
    private User $user;
    private User $otherUser;

    /** @var array<string, Episode> */
    private array $episodes = [];

    private int $popularWeight;
    private int $premieresWeight;

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            $this->fail('runing migrations failed' . PHP_EOL);
        }
        require_once __DIR__ . '/../../configs/path_constants.php';
        $this->container = require CONFIG_PATH . '/container/container.php';
        $this->episodeService = $this->container->get(EpisodeService::class);
        $this->em = $this->container->get(EntityManager::class);
        $this->config = $this->container->get(Config::class);
        $this->popularWeight = (int) $this->config->get('popular_weight');
        $this->premieresWeight = (int) $this->config->get('premieres_weight');

        $this->popularShow = $this->createShow(1, 'Popular Show', $this->popularWeight, 'HBO', 'Max');
        $this->unpopularShow = $this->createShow(
            2,
            'Unpopular Show',
            $this->popularWeight - 1,
            null,
            'Netflix'
        );
        $this->guestShow = $this->createShow(3, 'Guest Show', 1);
        $this->highWeightShow = $this->createShow(4, 'High Weight Show', $this->popularWeight + 100);
        $this->atThresholdShow = $this->createShow(5, 'Threshold Show', $this->premieresWeight);
        $this->aboveThresholdShow = $this->createShow(6, 'Above Threshold Show', $this->premieresWeight + 1);

        // UTC November: inside the month
        $this->episodes['popular-in-start'] = $this->createEpisode(
            $this->popularShow,
            1,
            'Popular Show S01E01',
            new DateTime('2026-11-01T00:00:00+00:00'),
            1,
            1,
            'Summary 1',
            'img.medium.1'
        );
        $this->episodes['popular-in-end'] = $this->createEpisode(
            $this->popularShow,
            2,
            'Popular Show S01E02',
            new DateTime('2026-11-30T23:59:00+00:00'),
            1,
            2,
            'Summary 2'
        );
        // October in every time zone used by the tests
        $this->episodes['popular-out-before'] = $this->createEpisode(
            $this->popularShow,
            3,
            'Popular Show S01E03',
            new DateTime('2026-10-31T23:59:00+00:00'),
            1,
            3
        );
        // December in UTC but still November in America/New_York
        $this->episodes['popular-out-after-utc'] = $this->createEpisode(
            $this->popularShow,
            4,
            'Popular Show S01E04',
            new DateTime('2026-12-01T00:00:00+00:00'),
            1,
            4
        );
        // December in every time zone used by the tests
        $this->episodes['popular-out-after'] = $this->createEpisode(
            $this->popularShow,
            5,
            'Popular Show S01E05',
            new DateTime('2026-12-01T05:00:00+00:00'),
            1,
            5
        );

        $this->episodes['unpopular-in'] = $this->createEpisode(
            $this->unpopularShow,
            6,
            'Unpopular Show S02E01',
            new DateTime('2026-11-15T12:00:00+00:00'),
            2,
            1
        );
        $this->episodes['unpopular-no-airstamp'] = $this->createEpisode(
            $this->unpopularShow,
            7,
            'Unpopular Show S02E02',
            null,
            2,
            2
        );

        $this->episodes['guest-in'] = $this->createEpisode(
            $this->guestShow,
            8,
            'Guest Show S03E01',
            new DateTime('2026-11-15T18:00:00+00:00'),
            3,
            1
        );
        $this->episodes['high-weight-in'] = $this->createEpisode(
            $this->highWeightShow,
            9,
            'High Weight Show S04E01',
            new DateTime('2026-11-20T10:00:00+00:00'),
            4,
            1
        );
        $this->episodes['at-threshold-in'] = $this->createEpisode(
            $this->atThresholdShow,
            10,
            'Threshold Show S05E01',
            new DateTime('2026-11-10T10:00:00+00:00'),
            5,
            1
        );
        $this->episodes['above-threshold-in'] = $this->createEpisode(
            $this->aboveThresholdShow,
            11,
            'Above Threshold Show S06E01',
            new DateTime('2026-11-25T10:00:00+00:00'),
            6,
            1
        );

        $this->em->flush();

        $this->user = $this->createUser('episode-service-user@example.com');
        $this->user->addShow($this->popularShow);
        $this->user->addShow($this->unpopularShow);

        // another subscriber must not leak episodes into $this->user's schedule
        $this->otherUser = $this->createUser('episode-service-other@example.com');
        $this->otherUser->addShow($this->guestShow);

        $this->em->flush();
    }

    public function testGetEpisodesForLoggedInUserReturnsOnlySubscribedShows(): void
    {
        $episodes = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-11-01'),
            'UTC',
            ScheduleType::User,
            $this->user,
            []
        );

        // the local list is ignored when a user is given, otherwise the high weight show would show up
        $episodesWithLocalList = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-11-01'),
            'UTC',
            ScheduleType::User,
            $this->user,
            [$this->highWeightShow->getId()]
        );

        $expected = [
            'Popular Show S01E01',
            'Popular Show S01E02',
            'Unpopular Show S02E01',
        ];

        $this->assertSame($expected, $this->getEpisodeNames($episodes));
        $this->assertSame($expected, $this->getEpisodeNames($episodesWithLocalList));
    }

    public function testGetEpisodesForGuestUsesTheLocalShowList(): void
    {
        $episodes = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-11-01'),
            'UTC',
            ScheduleType::User,
            null,
            [$this->guestShow->getId(), $this->popularShow->getId(), 99999]
        );

        // ordered by show id, then episode id
        $this->assertSame(
            [
                'Popular Show S01E01',
                'Popular Show S01E02',
                'Guest Show S03E01',
            ],
            $this->getEpisodeNames($episodes)
        );
    }

    public function testGetEpisodesForGuestWithEmptyLocalListReturnsNothing(): void
    {
        $episodes = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-11-01'),
            'UTC',
            ScheduleType::User,
            null,
            []
        );

        $this->assertSame([], $episodes);
    }

    public function testGetPopularEpisodesFiltersByWeight(): void
    {
        $episodes = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-11-01'),
            'UTC',
            ScheduleType::Popular,
            null,
            []
        );

        $this->assertSame(
            [
                'Popular Show S01E01',
                'Popular Show S01E02',
                'High Weight Show S04E01',
            ],
            $this->getEpisodeNames($episodes)
        );

        // ordered by show id ASC, then episode id ASC
        $showIds = array_column($episodes, 'showId');
        $sortedShowIds = $showIds;
        sort($sortedShowIds);
        $this->assertSame($sortedShowIds, $showIds);

        $popularShowEpisodeIds = array_map(
            fn(array $episode) => $episode['id'],
            array_filter(
                $episodes,
                fn(array $episode) => $episode['showId'] === $this->popularShow->getId()
            )
        );
        $sortedEpisodeIds = $popularShowEpisodeIds;
        sort($sortedEpisodeIds);
        $this->assertSame($sortedEpisodeIds, $popularShowEpisodeIds);
    }

    public function testGetPremieresReturnsOnlyPremieresOfShowsAtOrAboveTheConfiguredWeight(): void
    {
        $episodes = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-11-01'),
            'UTC',
            ScheduleType::Premieres,
            null,
            []
        );

        $episodeNames = $this->getEpisodeNames($episodes);

        $this->assertSame(
            [
                'Popular Show S01E01',
                'Unpopular Show S02E01',
                'High Weight Show S04E01',
                'Threshold Show S05E01',
                'Above Threshold Show S06E01',
            ],
            $episodeNames
        );

        // a season premiere counts as a premiere
        $this->assertContains('Unpopular Show S02E01', $episodeNames);
        $this->assertContains('Above Threshold Show S06E01', $episodeNames);
        // mid-season episodes are not premieres
        $this->assertNotContains('Popular Show S01E02', $episodeNames);
        // episodes outside the selected month are never returned
        $this->assertNotContains('Popular Show S01E03', $episodeNames);
        $this->assertNotContains('Popular Show S01E05', $episodeNames);
        // a show at exactly the configured weight is included
        $this->assertContains('Threshold Show S05E01', $episodeNames);
        // shows below the configured weight are excluded
        $this->assertNotContains('Guest Show S03E01', $episodeNames);
    }

    public function testMonthBoundariesFollowTheRequestedTimeZone(): void
    {
        $utcEpisodes = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-11-01'),
            'UTC',
            ScheduleType::Popular,
            null,
            []
        );

        $newYorkEpisodes = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-11-01'),
            'America/New_York',
            ScheduleType::Popular,
            null,
            []
        );

        // 2026-11-01 00:00 UTC is still October 31st in New York
        $this->assertContains('Popular Show S01E01', $this->getEpisodeNames($utcEpisodes));
        $this->assertNotContains('Popular Show S01E01', $this->getEpisodeNames($newYorkEpisodes));

        // 2026-12-01 00:00 UTC is still November 30th in New York
        $this->assertNotContains('Popular Show S01E04', $this->getEpisodeNames($utcEpisodes));
        $this->assertContains('Popular Show S01E04', $this->getEpisodeNames($newYorkEpisodes));

        $this->assertSame(
            [
                'Popular Show S01E02',
                'Popular Show S01E04',
                'High Weight Show S04E01',
            ],
            $this->getEpisodeNames($newYorkEpisodes)
        );
    }

    public function testGetEpisodesReturnsEmptyArrayWhenNoEpisodesAirInMonth(): void
    {
        $episodes = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-09-01'),
            'UTC',
            ScheduleType::Popular,
            null,
            []
        );

        $this->assertSame([], $episodes);
    }

    public function testGetEpisodesSkipsEpisodesWithoutAnAirstamp(): void
    {
        $episodes = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-11-01'),
            'UTC',
            ScheduleType::User,
            null,
            [$this->unpopularShow->getId()]
        );

        $this->assertSame(['Unpopular Show S02E01'], $this->getEpisodeNames($episodes));
    }

    public function testGetEpisodesReturnsTheExpectedRowShape(): void
    {
        $episodes = $this->episodeService->getEpisodesForMonth(
            new DateTime('2026-11-01'),
            'UTC',
            ScheduleType::User,
            null,
            [$this->popularShow->getId()]
        );

        $this->assertCount(2, $episodes);

        $episode = $episodes[0];
        $expectedEpisode = $this->episodes['popular-in-start'];

        $this->assertSame(
            [
                'id',
                'showName',
                'episodeName',
                'season',
                'number',
                'summary',
                'type',
                'airstamp',
                'image',
                'showId',
                'networkName',
                'webChannelName',
            ],
            array_keys($episode)
        );

        $this->assertSame($expectedEpisode->getId(), $episode['id']);
        $this->assertSame($this->popularShow->getId(), $episode['showId']);
        $this->assertSame('Popular Show', $episode['showName']);
        $this->assertSame('Popular Show S01E01', $episode['episodeName']);
        $this->assertSame(1, $episode['season']);
        $this->assertSame(1, $episode['number']);
        $this->assertSame('Summary 1', $episode['summary']);
        $this->assertSame('regular', $episode['type']);
        $this->assertSame('img.medium.1', $episode['image']);
        $this->assertSame('HBO', $episode['networkName']);
        $this->assertSame('Max', $episode['webChannelName']);
        $this->assertSame(
            (new DateTime('2026-11-01T00:00:00+00:00'))->getTimestamp(),
            (new DateTime($episode['airstamp']))->getTimestamp()
        );
    }

    private function createShow(
        int $tvMazeId,
        string $name,
        int $weight,
        ?string $networkName = null,
        ?string $webChannelName = null
    ): Show {
        $show = new Show();
        $show->setTvMazeId($tvMazeId);
        $show->setName($name);
        $show->setStatus('running');
        $show->setWeight($weight);
        $show->setNetworkName($networkName);
        $show->setWebChannelName($webChannelName);

        $this->em->persist($show);

        return $show;
    }

    private function createEpisode(
        Show $show,
        int $tvMazeEpisodeId,
        string $name,
        ?DateTime $airstamp,
        int $season,
        int $number,
        ?string $summary = null,
        ?string $imageMedium = null
    ): Episode {
        $episode = new Episode();
        $episode->setTvMazeEpisodeId($tvMazeEpisodeId);
        $episode->setTvMazeShowId($show->getTvMazeId());
        $episode->setName($name);
        $episode->setAirstamp($airstamp);
        $episode->setSeason($season);
        $episode->setNumber($number);
        $episode->setSummary($summary);
        $episode->setType('regular');
        $episode->setImageMedium($imageMedium);
        $episode->setShow($show);

        $this->em->persist($episode);

        return $episode;
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('12345678');
        $user->setStartOfWeekSunday(false);
        $user->setNotificationTime(NotificationTime::AIRTIME);
        $user->setNtfyTopic(null);

        $this->em->persist($user);

        return $user;
    }

    /**
     * @param array<int, array<string, mixed>> $episodes
     * @return string[]
     */
    private function getEpisodeNames(array $episodes): array
    {
        return array_column($episodes, 'episodeName');
    }

    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            $this->fail('db teardown failed' . PHP_EOL);
        }
    }
}
