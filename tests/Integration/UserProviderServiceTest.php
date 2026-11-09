<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\DataObjects\RegisterUserData;
use App\Entity\User;
use App\Enum\NotificationTime;
use App\Services\UserProviderService;
use DateTime;
use DI\Container;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

final class UserProviderServiceTest extends TestCase
{
    private Container $container;
    private EntityManager $entityManager;
    private UserProviderService $userProviderService;

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            $this->fail('running migrations failed' . PHP_EOL);
        }

        require_once __DIR__ . '/../../configs/path_constants.php';
        $this->container = require CONFIG_PATH . '/container/container.php';
        $this->entityManager = $this->container->get(EntityManager::class);
        $this->userProviderService = $this->container->get(UserProviderService::class);
    }

    public function testFindsAPreExistingMixedCaseEmailCaseInsensitively(): void
    {
        $user = $this->persistLegacyUser('Legacy@Example.COM');

        $found = $this->userProviderService->getByCredentials(['email' => ' legacy@example.com ']);

        $this->assertSame($user->getId(), $found->getId());
        $this->assertTrue($this->userProviderService->emailExists('LEGACY@EXAMPLE.COM'));
        $this->assertFalse($this->userProviderService->emailExists('other@example.com'));
    }

    public function testStoresNewUsersWithANormalizedEmail(): void
    {
        $user = $this->userProviderService->createUser(
            new RegisterUserData(email: ' NewUser@Example.COM ', password: 'password123')
        );

        $this->assertSame('newuser@example.com', $user->getEmail());
        $this->assertTrue($this->userProviderService->emailExists('newuser@example.com'));
    }

    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            $this->fail('db teardown failed' . PHP_EOL);
        }
    }

    private function persistLegacyUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('hashed-password');
        $user->setStartOfWeekSunday(false);
        $user->setNotificationTime(NotificationTime::AIRTIME);
        $user->setCreatedAt(new DateTime());
        $user->setUpdatedAt(new DateTime());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
