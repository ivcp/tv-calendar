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

    public function getSchedule(string $month, string $timeZone, string $type, ?User $user, array $localList): array
    {
        $selectedMonth = new DateTime($month);

        if (count($localList) > 0) {
            $localList = array_filter($localList, "is_numeric");
        }
        if (count($localList) > 10) {
            $localList = array_slice($localList, 0, 10);
        }

        $episodes = $this->episodeService->getEpisodesForMonth(
            $selectedMonth,
            $timeZone,
            $type,
            $user,
            $localList
        );

        $scheduleData = $this->getScheduleData($episodes);

        return ['episodes' => $scheduleData];
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
                airstamp: $episode['airstamp'],
                image: $episode['image'],
                networkName: $episode['networkName'],
                webChannelName:$episode['webChannelName']
            );
        }, $episodes);
    }
}
