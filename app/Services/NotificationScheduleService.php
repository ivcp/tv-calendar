<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Contracts\UserProviderServiceInterface;
use App\Enum\NotificationTime;
use Doctrine\ORM\EntityManager;
use RuntimeException;

class NotificationScheduleService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly NtfyService $ntfyService,
        private readonly UserProviderServiceInterface $userProvider,
        private readonly Config $config
    ) {}

    public function run(): void
    {
        $episodes = $this->getEpisodes();

        $currentShow = null;
        $currentShowAirstamp = null;
        foreach ($episodes as $episode) {
            if (
                $currentShow === $episode['showId'] &&
                $currentShowAirstamp === $episode['airstamp']
            ) {
                continue;
            }

            $currentShow = $episode['showId'];
            $currentShowAirstamp = $episode['airstamp'];

            [$title, $message, $showLink] = $this->formatNotification($episode);
            $topics = json_decode($episode['topics']);

            if (is_array($topics)) {
                foreach ($topics as $topic) {
                    $user = $this->userProvider->getByNtfyTopic($topic);

                    if ($user) {
                        $timestamp = match ($user->getNotificationTime()) {
                            NotificationTime::AIRTIME => strtotime($episode['airstamp']),
                            NotificationTime::ONE_HOUR_BEFORE => strtotime($episode['airstamp']) - 3600,
                            NotificationTime::ONE_HOUR_AFTER => strtotime($episode['airstamp']) + 3600,
                        };

                        $availableString =  match ($user->getNotificationTime()) {
                            NotificationTime::AIRTIME => 'Airing now',
                            NotificationTime::ONE_HOUR_BEFORE => 'Airing in one hour',
                            NotificationTime::ONE_HOUR_AFTER => 'Aired one hour ago',
                        };

                        $messageWithAiring = "$availableString\n\n" . $message;

                        try {
                            $this->ntfyService->sendNotification(
                                $topic,
                                $title,
                                $messageWithAiring,
                                $timestamp,
                                $showLink
                            );
                        } catch (RuntimeException $e) {
                            error_log("ERROR sending notification: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    public function formatNotification(array $episode): array
    {
        $title = $episode['showName'];
        if ($episode['season'] && $episode['number']) {
            $title = sprintf(
                '%s S%d E%d',
                $episode['showName'],
                $episode['season'],
                $episode['number']
            );
        }

        //special
        if ($episode['type'] && str_contains($episode['type'], 'special')) {
            $title .= ' (special)';
        }

        $summary = $episode['summary'] ?
            strip_tags($episode['summary']) :
            'Episode summary not available.';

        //premiere show show summary
        if ($episode['season'] === 1 && $episode['number'] === 1) {
            $summary = $episode['showSummary'] ?
                strip_tags($episode['showSummary']) :
                'Show summary not available.';
        }

        $channel = $episode['networkName'] ?
            $episode['networkName'] :  $episode['webChannelName'];
        if (!$channel) {
            $channel = '?';
        }

        $showLink = $this->config->get('app_url') . '/shows/' . $episode['showId'];

        $message = <<<MESSAGE
        Episode title: $episode[episodeName]

        Summary: $summary

        Available on: $channel
        MESSAGE;

        return [$title, $message, $showLink];
    }

    public function getEpisodes(): array
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT e.id, s.name as "showName", 
                e.name as "episodeName",
                e.season, e.number, e.summary, e.type, e.airstamp,
                e.image_medium as image, 
                s.id as "showId", 
                s.summary as "showSummary", 
                s.network_name as "networkName", 
                s.web_channel_name as "webChannelName",                
                JSON_AGG(DISTINCT u.ntfy_topic) AS topics
                FROM episodes e
                INNER JOIN shows s ON s.id = e.show_id
                INNER JOIN users_shows us ON us.show_id = s.id AND us.notifications_enabled = true
                INNER JOIN users u ON us.user_id = u.id AND u.ntfy_topic IS NOT NULL
                WHERE (e.airstamp BETWEEN now() + interval \'2 hours\' AND now() + interval \'3 hours\')
                GROUP BY
                    e.id,
                    s.id,
                    s.name,
                    e.name,
                    e.season,
                    e.number,
                    e.type,
                    e.airstamp,
                    e.image_medium,
                    s.network_name,
                    s.web_channel_name
                ORDER BY e.airstamp ASC, s.id ASC, e.number ASC                   
                ';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        return $resultSet->fetchAllAssociative();
    }
}
