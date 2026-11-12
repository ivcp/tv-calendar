<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\Email;
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
    private EntityManager $entityManager;

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            $this->fail('runing migrations failed' . PHP_EOL);
        }
        require_once __DIR__ . '/../../configs/path_constants.php';
        $this->container = require CONFIG_PATH . '/container/container.php';
        $this->entityManager = $this->container->get(EntityManager::class);

        // Too old for a reminder, deleted by this run.
        $this->createUser('delete-6d@example.com', '-6 days');
        // Too old for a reminder, deleted by this run.
        $this->createUser('delete-5d1h@example.com', '-5 days -1 hour');
        // Reminder-eligible via the main window.
        $this->createUser('remind-4d@example.com', '-4 days');
        // Reminder-eligible, just inside the upper cutoff.
        $this->createUser('remind-3d1h@example.com', '-3 days -1 hour');
        // Reminder-eligible after a missed run, deleted only on the next run.
        $this->createUser('remind-4d22h@example.com', '-4 days -22 hours');
        // Too young for a reminder.
        $this->createUser('young-2d@example.com', '-2 days');
        // Just registered.
        $this->createUser('young-now@example.com');
        // Verified long ago, never touched.
        $this->createUser('verified-6d@example.com', '-6 days', true);

        $this->entityManager->flush();
    }

    public function testAccountCleanup(): void
    {
        $service = $this->container->get(AccountCleanupService::class);

        $this->assertSame(2, $service->run());
        $this->entityManager->clear();

        $remindedEmails = [
            'remind-3d1h@example.com',
            'remind-4d@example.com',
            'remind-4d22h@example.com',
        ];
        sort($remindedEmails);

        $emails = $this->entityManager->getRepository(Email::class)->findAll();
        $recipients = array_map(fn(Email $email) => $email->getEmail(), $emails);
        sort($recipients);

        $this->assertSame($remindedEmails, $recipients);
        foreach ($emails as $email) {
            $this->assertSame('emails/deletion-reminder.html.twig', $email->getHtmlTemplate());
        }

        foreach ($remindedEmails as $address) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $address]);
            $this->assertNotNull($user);
            $this->assertNotNull($user->getDeletionNoticeSentAt());

            $email = $this->entityManager->getRepository(Email::class)->findOneBy(['email' => $address]);
            $expectedDeletionDate = (clone $user->getCreatedAt())->modify('+5 days');
            $this->assertSame(
                $expectedDeletionDate->getTimestamp(),
                $email->getExpirationDate()->getTimestamp()
            );
        }

        foreach (['young-2d@example.com', 'young-now@example.com', 'verified-6d@example.com'] as $address) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $address]);
            $this->assertNotNull($user);
            $this->assertNull($user->getDeletionNoticeSentAt());
        }

        $this->assertNull(
            $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'delete-6d@example.com'])
        );
        $this->assertNull(
            $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => 'delete-5d1h@example.com',
            ])
        );

        // Running the reminder step again must not queue duplicates.
        $this->assertSame(0, $service->sendDeletionReminders());
        $this->assertCount(3, $this->entityManager->getRepository(Email::class)->findAll());
    }

    private function createUser(string $email, ?string $createdAt = null, bool $verified = false): void
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('12345678');
        $user->setStartOfWeekSunday(false);
        $user->setNotificationTime(NotificationTime::AIRTIME);
        $user->setCreatedAt(new DateTime($createdAt ?? 'now'));
        $user->setUpdatedAt(new DateTime());
        if ($verified) {
            $user->setVerifiedAt(new DateTime('2 days ago'));
        }

        $this->entityManager->persist($user);
    }

    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            $this->fail('db teardown failed' . PHP_EOL);
        }
    }
}
