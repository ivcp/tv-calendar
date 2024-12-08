<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\ScheduleData;
use DateTime;

class ScheduleService
{

    public function getSchedule(string $month, string $JsonPath): array
    {
        $scheduleData = $this->loadData($JsonPath);

        $selectedMonth = new DateTime($month);
        $daysInMonth = $selectedMonth->format('t');

        $popular = $this->filterByPopular($scheduleData, $month);

        $popularSchedule = $this->sortByDates((int) $daysInMonth, $popular);

        //TODO: $myShowsSchedule


        return ['popular' => $popularSchedule, 'my_shows' => []];
    }

    private function loadData(string $JsonPath): array
    {
        $contents = file_get_contents($JsonPath);
        $data = json_decode($contents);
        return array_map(function ($show) {
            return new ScheduleData(
                $show->id,
                $show->_embedded->show->id,
                $show->name,
                $show->season,
                $show->number,
                $show->summary,
                $show->airstamp,
                $show->_embedded->show->name,
                $show->_embedded->show->weight
            );
        }, $data);
    }

    private function filterByPopular(array $scheduleData, string $month): array
    {
        return array_values(array_filter(
            $scheduleData,
            fn($show) =>
            $show->weight === 100 && (new DateTime($show->airstamp))->format('Y-m') === $month
        ));
    }

    private function sortByDates(int $daysInMonth, array $schedule): array
    {
        $sorted = [];

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $sorted[$i] = array_values(array_filter(
                $schedule,
                fn($show) => (new DateTime($show->airstamp))->format('j') == $i
            ));
        }
        return $sorted;
    }
}
