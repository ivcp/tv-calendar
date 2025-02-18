<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\EpisodeInfoData;
use App\Entity\User;
use DateTime;

class CalendarService
{
    public function __construct(private readonly EpisodeService $episodeService)
    {
    }

    public function getSchedule(string $month, ?User $user = null): array
    {
        $selectedMonth = new DateTime($month);

        $episodesPopular = $this->episodeService->getEpisodesForMonth($selectedMonth);
        $userEpisodes = [];
        if ($user) {
            $userEpisodes = $this->episodeService->getEpisodesForMonth($selectedMonth, $user);
        }

        $popularScheduleData = $this->getScheduleData($episodesPopular);
        $popularSchedule = $this->sortByDates((int) $selectedMonth->format('t'), $popularScheduleData);

        $userSchedule = [];
        if ($user) {
            $userScheduleData = $this->getScheduleData($userEpisodes);
            $userSchedule = $this->sortByDates((int) $selectedMonth->format('t'), $userScheduleData);
        }

        return ['popular' => $popularSchedule, 'user_shows' => $userSchedule];
    }



    private function sortByDates(int $daysInMonth, array $episodes): array
    {
        $sorted = [];

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $sorted[$i] = array_values(array_filter(
                $episodes,
                fn ($show) => (new DateTime($show->airstamp))->format('j') == $i
            ));
        }
        return $sorted;
    }

    private function getScheduleData(array $episodes): array
    {
        return array_map(function ($episode) {
            return new EpisodeInfoData(
                id: $episode['id'],
                showId: $episode['showId'],
                showName: $episode['showName'],
                episodeName: $episode['episodeName'],
                seasonNumber: $episode['season'],
                episodeNumber: $episode['number'],
                episodeSummary: $episode['summary'],
                type: $episode['type'],
                airstamp: $episode['airstamp']?->format(DATE_ATOM),
                image: $episode['image'],
                networkName: $episode['networkName'],
                webChannelName:$episode['webChannelName']
            );
        }, $episodes);
    }
}
