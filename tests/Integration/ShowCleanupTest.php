<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Config;
use App\Entity\Episode;
use App\Entity\Show;
use App\Entity\User;
use App\Entity\UserShows;
use App\Enum\NotificationTime;
use App\Services\ShowCleanupService;
use App\Services\ShowService;
use App\Services\TvMazeService;
use App\Services\WebhookService;
use DI\Container;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

final class ShowCleanupTest extends TestCase
{
    private Container $container;
    private EntityManager $entityManager;

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            $this->fail('running migrations failed' . PHP_EOL);
        }

        require_once __DIR__ . '/../../configs/path_constants.php';
        $this->container = require CONFIG_PATH . '/container/container.php';
        $this->entityManager = $this->container->get(EntityManager::class);
    }

    public function testDryRunReportsDeadShowsWithoutDeletingAnything(): void
    {
        [$liveShow, $deadShow, $stillLiveShow, $unavailableShow] = $this->seedShows();

        $service = $this->service($this->tvMazeService());

        $stats = $this->runService($service, dryRun: true);

        $this->assertSame(4, $stats['local']);
        $this->assertSame(1, $stats['live']);
        $this->assertSame(3, $stats['candidates']);
        $this->assertSame(1, $stats['dead']);
        $this->assertSame(1, $stats['kept']);
        $this->assertSame(1, $stats['errors']);
        $this->assertSame(0, $stats['deleted']);
        $this->assertSame(2, $stats['episodes']);
        $this->assertSame(1, $stats['subscriptions']);

        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->find(Show::class, $deadShow->getId()));
        $this->assertSame(2, $this->episodeCount($deadShow->getId()));
        $this->assertSame(1, $this->subscriptionCount($deadShow->getId()));
        $this->assertSame(1, $this->episodeCount($liveShow->getId()));
        $this->assertNotNull($this->entityManager->find(Show::class, $stillLiveShow->getId()));
        $this->assertNotNull($this->entityManager->find(Show::class, $unavailableShow->getId()));
    }

    public function testApplyDeletesConfirmedDeadShowsAndTheirDependents(): void
    {
        [$liveShow, $deadShow, $stillLiveShow, $unavailableShow] = $this->seedShows();

        $service = $this->service($this->tvMazeService());

        $stats = $this->runService($service, dryRun: false);

        $this->assertSame(1, $stats['deleted']);
        $this->assertSame(1, $stats['dead']);

        $this->entityManager->clear();

        $this->assertNull($this->entityManager->find(Show::class, $deadShow->getId()));
        $this->assertSame(0, $this->episodeCount($deadShow->getId()));
        $this->assertSame(0, $this->subscriptionCount($deadShow->getId()));

        $this->assertNotNull($this->entityManager->find(Show::class, $liveShow->getId()));
        $this->assertSame(1, $this->episodeCount($liveShow->getId()));

        // Missing from the live list but still answering 200: kept.
        $this->assertNotNull($this->entityManager->find(Show::class, $stillLiveShow->getId()));
        // Inconclusive check: kept for the next run.
        $this->assertNotNull($this->entityManager->find(Show::class, $unavailableShow->getId()));

        // A second run is a no-op because the dead show is gone.
        $stats = $this->runService($service, dryRun: false);
        $this->assertSame(0, $stats['deleted']);
        $this->assertSame(2, $stats['candidates']);
        $this->assertSame(1, $stats['kept']);
        $this->assertSame(1, $stats['errors']);
    }

    public function testAbortsWhenTheLiveShowListCannotBeFetched(): void
    {
        $show = $this->createShow(1001, 'Live Show');
        $this->entityManager->flush();

        $tvMazeService = $this->createStub(TvMazeService::class);
        $tvMazeService->method('getLiveShowIds')->willReturn(null);

        $stats = $this->runService($this->service($tvMazeService), dryRun: false);

        $this->assertSame(0, $stats['live']);
        $this->assertSame(0, $stats['candidates']);
        $this->assertSame(0, $stats['deleted']);
        $this->assertSame(1, $stats['errors']);

        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->find(Show::class, $show->getId()));
    }

    public function testAbortsWhenTheLiveShowListIsEmpty(): void
    {
        $show = $this->createShow(1001, 'Live Show');
        $this->entityManager->flush();

        $tvMazeService = $this->createStub(TvMazeService::class);
        $tvMazeService->method('getLiveShowIds')->willReturn([]);

        $stats = $this->runService($this->service($tvMazeService), dryRun: false);

        $this->assertSame(0, $stats['candidates']);
        $this->assertSame(0, $stats['deleted']);
        $this->assertSame(1, $stats['errors']);

        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->find(Show::class, $show->getId()));
    }

    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            $this->fail('db teardown failed' . PHP_EOL);
        }
    }

    /**
     * @return array{Show, Show, Show, Show}
     */
    private function seedShows(): array
    {
        $liveShow = $this->createShow(1001, 'Live Show');
        $deadShow = $this->createShow(2002, 'Dead Show');
        $stillLiveShow = $this->createShow(3003, 'Missing From Live List');
        $unavailableShow = $this->createShow(4004, 'Inconclusive Check');

        $this->createEpisode($liveShow, 10001);
        $this->createEpisode($deadShow, 20001);
        $this->createEpisode($deadShow, 20002);

        $user = new User();
        $user->setEmail('cleanup@example.com');
        $user->setPassword('12345678');
        $user->setStartOfWeekSunday(false);
        $user->setNotificationTime(NotificationTime::AIRTIME);
        $this->entityManager->persist($user);

        $subscription = new UserShows();
        $subscription->setUser($user);
        $subscription->setShow($deadShow);
        $this->entityManager->persist($subscription);

        $this->entityManager->flush();

        return [$liveShow, $deadShow, $stillLiveShow, $unavailableShow];
    }

    private function createShow(int $tvMazeId, string $name): Show
    {
        $show = new Show();
        $show->setTvMazeId($tvMazeId);
        $show->setName($name);
        $show->setStatus('running');
        $show->setWeight(100);

        $this->entityManager->persist($show);

        return $show;
    }

    private function createEpisode(Show $show, int $tvMazeEpisodeId): Episode
    {
        $episode = new Episode();
        $episode->setShow($show);
        $episode->setTvMazeShowId($show->getTvMazeId());
        $episode->setTvMazeEpisodeId($tvMazeEpisodeId);
        $episode->setName('Episode ' . $tvMazeEpisodeId);
        $episode->setType('regular');

        $this->entityManager->persist($episode);

        return $episode;
    }

    private function tvMazeService(): TvMazeService
    {
        $tvMazeService = $this->createStub(TvMazeService::class);
        $tvMazeService->method('getLiveShowIds')->willReturn([1001]);
        $tvMazeService->method('isShowGone')->willReturnCallback(
            fn(int $tvMazeId): ?bool => match ($tvMazeId) {
                2002 => true,
                3003 => false,
                4004 => null,
                default => null,
            }
        );

        return $tvMazeService;
    }

    private function service(TvMazeService $tvMazeService): ShowCleanupService
    {
        return new ShowCleanupService(
            new ShowService($this->entityManager),
            $tvMazeService,
            $this->createStub(WebhookService::class),
            $this->container->get(Config::class),
            0
        );
    }

    /**
     * Keep the cleanup report out of PHPUnit's output.
     *
     * @return array{
     *     dryRun: bool,
     *     local: int,
     *     live: int,
     *     candidates: int,
     *     dead: int,
     *     kept: int,
     *     deleted: int,
     *     episodes: int,
     *     subscriptions: int,
     *     errors: int
     * }
     */
    private function runService(ShowCleanupService $service, bool $dryRun): array
    {
        ob_start();
        try {
            return $service->run($dryRun);
        } finally {
            ob_end_clean();
        }
    }

    private function episodeCount(int $showId): int
    {
        return (int) $this->entityManager->getConnection()
            ->executeQuery('SELECT count(*) FROM episodes WHERE show_id = ?', [$showId])
            ->fetchOne();
    }

    private function subscriptionCount(int $showId): int
    {
        return (int) $this->entityManager->getConnection()
            ->executeQuery('SELECT count(*) FROM users_shows WHERE show_id = ?', [$showId])
            ->fetchOne();
    }
}
