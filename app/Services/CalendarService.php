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

        //TODO:

        $episodesPopular = $this->episodeService->getEpisodesForMonth($selectedMonth, 'CET');
        $userEpisodes = [];
        if ($user) {
            $userEpisodes = $this->episodeService->getEpisodesForMonth($selectedMonth, 'CET', $user);
        }



        $popularSchedule = $this->getScheduleData($episodesPopular);
        $userSchedule = [];
        if ($user) {
            $userSchedule = $this->getScheduleData($userEpisodes);
        }

        return ['popular' => $popularSchedule, 'user_shows' => $userSchedule];
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
