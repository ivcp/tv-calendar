<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\DataObjects\ShowData;
use App\Entity\Episode;
use App\Entity\Show;
use App\Entity\User;
use App\Entity\UserShows;
use App\Enum\NotificationTime;
use App\Services\AccountCleanupService;
use App\Services\NotificationScheduleService;
use DateTime;
use DI\Container;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

final class NotificationScheduleServiceTest extends TestCase
{
    private Container $container;
    private NotificationScheduleService $notificationScheduleService;
    private EntityManager $em;


    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            $this->fail('runing migrations failed' . PHP_EOL);
        }
        require_once __DIR__ . '/../../configs/path_constants.php';
        $this->container = require CONFIG_PATH . '/container/container.php';
        $this->notificationScheduleService = $this->container->get(NotificationScheduleService::class);
        $this->em = $this->container->get(EntityManager::class);

        for ($i = 1; $i <= 10; $i++) {
            $show = new Show();
            $show->setTvMazeId($i);
            $show->setName("test show $i");
            $show->setStatus('running');
            $show->setWeight(100);

            $ep = new Episode();
            $ep->setShow($show);
            $ep->setTvMazeShowId($i);
            $ep->setTvMazeEpisodeId($i);
            $ep->setName("episode-$i");
            if ($i === 10) {
                $ep->setAirstamp(new DateTime('-2 hours'));
            } elseif ($i === 9) {
                $ep->setAirstamp(new DateTime('+25 hours'));
            } else {
                $ep->setAirstamp(new DateTime('+121 minutes'));
            }
            if ($i === 7) {
                for ($e = 2; $e <= 10; $e++) {
                    $ep = new Episode();
                    $ep->setShow($show);
                    $ep->setTvMazeShowId($i);
                    $ep->setTvMazeEpisodeId(10 + $e);
                    $ep->setName("episode-" . $e);
                    $ep->setAirstamp(new DateTime('+180 minutes'));
                }
            }

            $show->addEpisode($ep);
            $this->em->persist($show);
        }

        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setEmail("test$i@email.com");
            $user->setPassword('12345678');
            $user->setStartOfWeekSunday(false);
            $user->setNotificationTime(NotificationTime::AIRTIME);
            if ($i === 10) {
                $user->setNtfyTopic(null);
            } else {
                $user->setNtfyTopic("test$i");
            }
            $this->em->persist($user);
        }
        $this->em->flush();
    }

    public function testGetEpisodes(): void
    {

        $episodes =  $this->notificationScheduleService->getEpisodes();
        $this->assertCount(0, $episodes);

        $user = $this->em->getRepository(User::class)->find(1);
        $show = $this->em->getRepository(Show::class)->find(1);

        //user adds one show
        $this->addShowForUser($user, $show);

        $episodes =  $this->notificationScheduleService->getEpisodes();
        $this->assertCount(1, $episodes);
        $this->assertSame($episodes[0]['topics'], '["test1"]');

        //user adds 4 more shows
        for ($i = 2; $i <= 5; $i++) {
            $show = $this->em->getRepository(Show::class)->find($i);
            $this->addShowForUser($user, $show);
        }

        $this->em->flush();
        $episodes =  $this->notificationScheduleService->getEpisodes();
        $this->assertCount(5, $episodes);

        //user2 adds one show
        $user2 = $this->em->getRepository(User::class)->find(2);
        $show = $this->em->getRepository(Show::class)->find(1);
        $this->addShowForUser($user2, $show);

        $episodes = $this->notificationScheduleService->getEpisodes();
        $this->assertCount(5, $episodes);
        $this->assertSame('episode-1', $episodes[0]['episodeName']);
        //assert both user topics are there
        $this->assertSame($episodes[0]['topics'], '["test1", "test2"]');

        //user3 adds shows whose episodes don't air in next 24h, nothing changes
        $user3 = $this->em->getRepository(User::class)->find(3);
        $show1 = $this->em->getRepository(Show::class)->find(10);
        $show2 = $this->em->getRepository(Show::class)->find(9);
        $this->addShowForUser($user3, $show1);
        $this->addShowForUser($user3, $show2);

        $episodes = $this->notificationScheduleService->getEpisodes();
        $this->assertCount(5, $episodes);

        //user with disabled notifications adds show, nothing changes
        $user10 = $this->em->getRepository(User::class)->find(10);
        $show = $this->em->getRepository(Show::class)->find(1);
        $this->addShowForUser($user10, $show);

        $episodes = $this->notificationScheduleService->getEpisodes();
        $this->assertCount(5, $episodes);
        $this->assertSame($episodes[0]['topics'], '["test1", "test2"]');


        //user4 adds show with multiple episodes airing
        $user4 = $this->em->getRepository(User::class)->find(4);
        $show = $this->em->getRepository(Show::class)->find(7);
        $this->addShowForUser($user4, $show);

        $episodes = $this->notificationScheduleService->getEpisodes();
        $this->assertCount(15, $episodes);
        $eps = array_filter($episodes, fn($ep) => $ep['showName'] === 'test show 7');
        $this->assertCount(10, $eps);
        foreach ($eps as $ep) {
            $this->assertSame($ep['topics'], '["test4"]');
        }


        //user1 adds show, but disables notification for it
        $user = $this->em->getRepository(User::class)->find(1);
        $show = $this->em->getRepository(Show::class)->find(7);
        $this->addShowForUser($user, $show, true);

        $episodes =  $this->notificationScheduleService->getEpisodes();
        //nothing changes
        $this->assertCount(15, $episodes);
        $eps = array_filter($episodes, fn($ep) => $ep['showName'] === 'test show 7');
        $this->assertCount(10, $eps);
        foreach ($eps as $ep) {
            $this->assertSame($ep['topics'], '["test4"]');
        }
    }

    private function addShowForUser(User $user, Show $show, bool $isEnabled = false): void
    {
        $us = new UserShows();
        $us->setUser($user);
        $us->setShow($show);
        if ($isEnabled) {
            $us->setNotificationsEnabled(false);
        }

        $this->em->persist($us);
        $this->em->flush();
    }



    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            $this->fail('db teardown failed' . PHP_EOL);
        }
    }
}
