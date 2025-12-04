<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\User;
use App\Enum\NotificationTime;
use App\Services\NotificationScheduleService;
use App\Services\NtfyService;
use App\Services\UserProviderService;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotificationScheduleServiceTest extends TestCase
{
    #[DataProvider('episodeProvider')]
    public function testFormatNotification(
        array $episode,
        string $expectedTitle,
        string $expectedMessage,
        NotificationTime $notificationTime,
        int $expectedNotificationTime
    ): void {

        $dummyEm = $this->createStub(EntityManager::class);
        $dummyNtfy = $this->createStub(NtfyService::class);
        $dummyUserProvider = $this->createStub(UserProviderService::class);
        $user = new User();
        $user->setNotificationTime($notificationTime);
        $dummyUserProvider->method('getById')->willReturn($user);
        $notificationScheduleService = new NotificationScheduleService($dummyEm, $dummyNtfy, $dummyUserProvider);

        [$title, $message, $timestamp] = $notificationScheduleService->formatNotification($episode);

        $this->assertSame($expectedTitle, $title);
        $this->assertSame($expectedMessage, $message);
        $this->assertSame($expectedNotificationTime, $timestamp);

    }

    public static function episodeProvider(): array
    {

        $episode = [
            'id' => 1,
            'showName' => 'test show',
            'showSummary' => 'test show summary',
            'episodeName' => 'episode-1',
            'season' => 1,
            'number' => 2,
            'summary' => 'summary',
            'type' => 'regular',
            'airstamp' => '2025-12-03 15:31:25+00',
            'image' => 'img',
            'showId' => 1,
            'networkName' => null,
            'webChannelName' => null,
            'userId' => 1,
            'topics' => ["test1", "test2"]
            ];

        return [
            'title and message' => [
                $episode,
                'test show S1 E2',
                'summary',
                NotificationTime::AIRTIME,
                1764775885
            ],
            'title no SE, summary unavailable' => [
                array_replace($episode, ['season' => null, 'summary' => null]),
                'test show',
                'Episode summary not available.',
                NotificationTime::ONE_HOUR_BEFORE,
                1764772285
            ],
            'season premiere' => [
                array_replace($episode, [
                    'season' => 1, 'number' => 1
                ]),
                'test show S1 E1',
                'test show summary',
                NotificationTime::ONE_HOUR_AFTER,
                1764779485
            ],
        ];
    }



}
