<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Config;
use App\Entity\User;
use App\Mail\DeletionReminderEmail;
use App\Mail\VerificationLinkService;
use App\Services\EmailSendingService;
use App\Services\UserShowsService;
use DateTime;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class DeletionReminderEmailTest extends TestCase
{
    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail('viewer@example.com');

        $reflection = new ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, 42);

        return $user;
    }

    private function makeLinkService(): VerificationLinkService
    {
        return new VerificationLinkService(new Config([
            'app_url' => 'https://tv-calendar.test',
            'app_key' => 'test-app-key',
        ]));
    }

    private function makeMail(
        EmailSendingService $emailSendingService,
        UserShowsService $userShowsService
    ): DeletionReminderEmail {
        return new DeletionReminderEmail(
            new Config(['app_name' => 'TV Calendar']),
            $this->makeLinkService(),
            $emailSendingService,
            $userShowsService
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

        $userShowsService = $this->createMock(UserShowsService::class);

        $user = $this->makeUser();

        $userShowsService->expects($this->once())
            ->method('countShows')
            ->with($user)
            ->willReturn(2);

        $this->makeMail($emailSendingService, $userShowsService)->send($user, $deletionDate);
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

        $userShowsService = $this->createMock(UserShowsService::class);
        $userShowsService->method('countShows')->willReturn(0);

        $this->makeMail($emailSendingService, $userShowsService)->send($this->makeUser(), $deletionDate);
    }
}
