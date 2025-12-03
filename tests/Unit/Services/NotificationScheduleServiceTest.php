<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\NotificationScheduleService;
use App\Services\NtfyService;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotificationScheduleServiceTest extends TestCase
{
    #[DataProvider('episodeProvider')]
    public function testFormatNotification(array $episode, string $expectedTitle, string $expectedMessage): void
    {

        $dummyEm = $this->createStub(EntityManager::class);
        $dummyNtfy = $this->createStub(NtfyService::class);
        $notificationScheduleService = new NotificationScheduleService($dummyEm, $dummyNtfy);

        [$title, $message] = $notificationScheduleService->formatNotification($episode);

        $this->assertSame($expectedTitle, $title);
        $this->assertSame($expectedMessage, $message);

    }

    public static function episodeProvider(): array
    {

        $episode = [
            'id' => 1,
            'showName' => 'test show',
            'episodeName' => 'episode-1',
            'season' => 1,
            'number' => 1,
            'summary' => 'summary',
            'type' => 'regular',
            'airstamp' => '2025-12-03 15:31:25+00',
            'image' => 'img',
            'showId' => 1,
            'networkName' => null,
            'webChannelName' => null,
            'topics' => ["test1", "test2"]
            ];

        return [
            'title and message' => [$episode, 'test show S1 E1', 'summary'],
            'title no SE, summary unavailable' => [
                array_replace($episode, ['season' => null, 'summary' => null]),
                'test show', 'Episode summary not available.']
        ];
    }



}
