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

        // Reminder-eligible via the main window.
        $this->createUser('remind-4d@example.com', '-4 days');
        // Reminder-eligible, just inside the upper cutoff.
        $this->createUser('remind-3d1h@example.com', '-3 days -1 hour');
        // Reminder-eligible near the end of the window, deleted after the lead time.
        $this->createUser('remind-4d22h@example.com', '-4 days -22 hours');
        // Overdue and never warned: gets a catch-up reminder instead of being skipped.
        $this->createUser('overdue-6d@example.com', '-6 days');
        $this->createUser('overdue-5d1h@example.com', '-5 days -1 hour');
        // Too young for a reminder.
        $this->createUser('young-2d@example.com', '-2 days');
        // Just registered.
        $this->createUser('young-now@example.com');
        // Verified long ago, never touched.
        $this->createUser('verified-6d@example.com', '-6 days', true);
        // Warned at least the lead time ago, deleted by this run.
        $this->createUser('delete-warned-49h@example.com', '-6 days', false, '-49 hours');
        $this->createUser('delete-warned-48h@example.com', '-6 days', false, '-48 hours');
        // Warned less than the lead time ago, kept until the lead time has passed.
        $this->createUser('keep-warned-47h@example.com', '-6 days', false, '-47 hours');
        // Warned, but verified afterwards: deletion must not touch it.
        $this->createUser('keep-warned-verified@example.com', '-6 days', true, '-49 hours');

        $this->entityManager->flush();
    }

    public function testAccountCleanup(): void
    {
        $service = $this->container->get(AccountCleanupService::class);

        $this->assertSame(2, $service->run());
        $this->entityManager->clear();

        $remindedEmails = [
            'overdue-5d1h@example.com',
            'overdue-6d@example.com',
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

            // The reminder promises deletion one lead time after it was sent.
            $email = $this->entityManager->getRepository(Email::class)->findOneBy(['email' => $address]);
            $expectedDeletionDate = (clone $user->getDeletionNoticeSentAt())->modify('+48 hours');
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
            $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => 'delete-warned-49h@example.com',
            ])
        );
        $this->assertNull(
            $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => 'delete-warned-48h@example.com',
            ])
        );

        // Unwarned accounts are never deleted, however old they are.
        $this->assertNotNull(
            $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => 'overdue-6d@example.com',
            ])
        );
        // Warned too recently, or verified after the warning: kept.
        $this->assertNotNull(
            $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => 'keep-warned-47h@example.com',
            ])
        );
        $this->assertNotNull(
            $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => 'keep-warned-verified@example.com',
            ])
        );

        // Running again right away must not delete the accounts just warned,
        // and must not queue duplicate reminders.
        $this->assertSame(0, $service->run());
        $this->assertSame(0, $service->sendDeletionReminders());
        $this->assertCount(5, $this->entityManager->getRepository(Email::class)->findAll());
        $this->assertNotNull(
            $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => 'overdue-6d@example.com',
            ])
        );
    }

    private function createUser(
        string $email,
        ?string $createdAt = null,
        bool $verified = false,
        ?string $noticeSentAt = null
    ): void {
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
        if ($noticeSentAt !== null) {
            $user->setDeletionNoticeSentAt(new DateTime($noticeSentAt));
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
