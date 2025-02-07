<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CalendarService;
use App\Services\EpisodeService;
use DateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalendarServiceTest extends TestCase
{
    public static function monthProvider(): array
    {
        return [
            'december 2024' => ['2024-12', 1, 'test show'],
            'february 2025' => ['2025-02', 6, 'test new name'],
            'december 1999' => ['1999-12', 1, 'test'],
        ];
    }

    #[DataProvider('monthProvider')]
    public function testGetsScheduleForMonth(
        string $month,
        int $date,
        string $expectedShow,
    ): void {


        $episodeService = $this->createMock(EpisodeService::class);
        $episodeService->expects($this->any())->method('getEpisodesForMonth')->willReturn([[
            'id' => 1,
            'showName' => $expectedShow,
            'episodeName' => 'ep name',
            'season' => 1,
            'number' => 42,
            'summary' => 'a short summary',
            'type' => 'regular',
            'airstamp' => new DateTime($month . "-$date", new DateTimeZone('UTC'))
        ]]);

        $scheduleService = new CalendarService($episodeService);
        $schedule = $scheduleService->getSchedule($month);


        $days = (new DateTime($month))->format('t');
        $this->assertCount((int)$days, $schedule['popular']);
        $this->assertSame($expectedShow, $schedule['popular'][$date][0]->showName);
    }
}
