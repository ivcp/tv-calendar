<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\User;
use App\Enum\NotificationTime;
use App\Services\AccountCleanupService;
use DateTime;
use DI\Container;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

final class AccountCleanupTest extends TestCase
{
    private Container $container;

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            $this->fail('runing migrations failed' . PHP_EOL);
        }
        require_once __DIR__ . '/../../configs/path_constants.php';
        $this->container = require CONFIG_PATH . '/container/container.php';
        $entityManager =  $this->container->get(EntityManager::class);
        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setEmail("test$i@email.com");
            $user->setPassword('12345678');
            $user->setStartOfWeekSunday(false);
            $user->setNotificationTime(NotificationTime::AIRTIME);
            $user->setCreatedAt($i % 2 !== 0 ? new DateTime('6 days ago') : new DateTime());
            $user->setUpdatedAt(new DateTime());
            if ($i === 3 || $i === 7) {
                $user->setVerifiedAt(new DateTime('2 days ago'));
            } else {
                $user->setVerifiedAt($i % 2 !== 0 ? null : new DateTime());
            }
            $entityManager->persist($user);
        }
        $entityManager->flush();
    }

    public function testAccountCleanup(): void
    {
        $numDeleted = $this->container->get(AccountCleanupService::class)->run();
        $this->assertSame(3, $numDeleted);
    }

    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            $this->fail('db teardown failed' . PHP_EOL);
        }
    }
}
