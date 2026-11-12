<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Config;
use App\Entity\Show;
use App\Entity\User;
use App\Mail\DeletionReminderEmail;
use App\Mail\VerificationLinkService;
use App\Services\EmailSendingService;
use DateTime;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class DeletionReminderEmailTest extends TestCase
{
    private function makeUser(int $shows = 0): User
    {
        $user = new User();
        $user->setEmail('viewer@example.com');

        $reflection = new ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, 42);

        for ($i = 0; $i < $shows; $i++) {
            $user->addShow(new Show());
        }

        return $user;
    }

    private function makeLinkService(): VerificationLinkService
    {
        return new VerificationLinkService(new Config([
            'app_url' => 'https://tv-calendar.test',
            'app_key' => 'test-app-key',
        ]));
    }

    private function makeMail(EmailSendingService $emailSendingService): DeletionReminderEmail
    {
        return new DeletionReminderEmail(
            new Config(['app_name' => 'TV Calendar']),
            $this->makeLinkService(),
            $emailSendingService
        );
    }

    public function testItQueuesTheReminderForAnUnverifiedUser(): void
    {
        $deletionDate = new DateTime('+2 days');
        $linkExpiration = (clone $deletionDate)->modify('+1 day')->getTimestamp();
        $expectedLinkPrefix = 'https://tv-calendar.test/verify/42/' . sha1('viewer@example.com')
            . '?expiration=' . $linkExpiration;

        $emailSendingService = $this->createMock(EmailSendingService::class);
        $emailSendingService->expects($this->once())
            ->method('queue')
            ->with(
                'viewer@example.com',
                'Confirm your email to keep your TV Calendar account',
                'emails/deletion-reminder.html.twig',
                $this->stringStartsWith($expectedLinkPrefix),
                $deletionDate,
                null,
                2
            );

        $this->makeMail($emailSendingService)->send($this->makeUser(2), $deletionDate);
    }

    public function testItReportsZeroShowsForAnEmptyList(): void
    {
        $deletionDate = new DateTime('+2 days');

        $emailSendingService = $this->createMock(EmailSendingService::class);
        $emailSendingService->expects($this->once())
            ->method('queue')
            ->with(
                'viewer@example.com',
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                0
            );

        $this->makeMail($emailSendingService)->send($this->makeUser(), $deletionDate);
    }
}
