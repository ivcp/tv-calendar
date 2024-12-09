<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ScheduleService;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScheduleServiceTest extends TestCase
{

    public static function monthProvider(): array
    {
        return [
            'december 2024' => ['2024-12', 1, 1, 'Lioness'],
            'february 2025' => ['2025-02', 1, 28, 'Severance'],
            'december 2nd 2024' => ['2024-12', 0, 2, null],
            'december 1999' => ['1999-12', 0, 1, null],
            'january 2050' => ['2050-01', 0, 1, null]
        ];
    }

    #[DataProvider('monthProvider')]
    public function test_gets_schedule_for_current_month(
        string $month,
        int $expectedShowCount,
        int $airdate,
        ?string $expectedShow
    ): void {

        $scheduleService = new ScheduleService();
        $schedule = $scheduleService->getSchedule($month, __DIR__ . '/test_json.json');

        $days = (new DateTime($month))->format('t');

        $this->assertCount((int)$days, $schedule['popular']);
        $this->assertCount($expectedShowCount, $schedule['popular'][$airdate]);
        if (array_key_exists(0, $schedule['popular'][$airdate])) {
            $this->assertSame($expectedShow, $schedule['popular'][$airdate][0]->showName);
        }
    }
}
