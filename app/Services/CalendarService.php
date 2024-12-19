<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\ScheduleData;
use DateTime;

class CalendarService
{

    public function __construct(private readonly EpisodeService $episodeService) {}

    public function getSchedule(string $month): array
    {
        $selectedMonth = new DateTime($month);

        $episodes = $this->episodeService->getEpisodesForMonth($selectedMonth);

        $scheduleData = array_map(function ($episode) {
            return new ScheduleData(
                id: $episode['id'],
                showName: $episode['showName'],
                episodeName: $episode['episodeName'],
                seasonNumber: $episode['season'],
                episodeNumber: $episode['number'],
                episodeSummary: $episode['summary'],
                type: $episode['type'],
                airstamp: $episode['airstamp']->format(DATE_ATOM)
            );
        }, $episodes);

        $popularSchedule = $this->sortByDates((int) $selectedMonth->format('t'), $scheduleData);

        //TODO: my_shows
        return ['popular' => $popularSchedule, 'my_shows' => []];
    }



    private function sortByDates(int $daysInMonth, array $episodes): array
    {
        $sorted = [];

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $sorted[$i] = array_values(array_filter(
                $episodes,
                fn($show) => (new DateTime($show->airstamp))->format('j') == $i
            ));
        }
        return $sorted;
    }
}
