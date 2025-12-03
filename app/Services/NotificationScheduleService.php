<?php

declare(strict_types=1);

namespace App\Services;

use Doctrine\ORM\EntityManager;
use RuntimeException;

class NotificationScheduleService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly NtfyService $ntfyService
    ) {
    }

    public function run(): void
    {

        $episodes = $this->getEpisodes();

        foreach ($episodes as $episode) {
            [$title, $message] = $this->formatNotification($episode);
            $topics = json_decode($episode['topics']);

            if (is_array($topics)) {
                foreach ($topics as $topic) {
                    try {
                        $this->ntfyService->sendNotification($topic, $title, $message);
                    } catch (RuntimeException $e) {
                        error_log("ERROR sending notification: " . $e->getMessage());
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

        $message = $episode['summary'] ?
                    strip_tags($episode['summary']) :
                    'Episode summary not available.';

        return [$title, $message];

    }

    public function getEpisodes(): array
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT e.id, s.name as "showName", 
                e.name as "episodeName",
                e.season, e.number, e.summary, e.type, e.airstamp,
                e.image_medium as image, 
                s.id as "showId", 
                s.network_name as "networkName", 
                s.web_channel_name as "webChannelName", 
                JSON_AGG(DISTINCT u.ntfy_topic) AS topics
                FROM episodes e
                INNER JOIN shows s ON s.id = e.show_id
                INNER JOIN users_shows us ON us.show_id = s.id AND us.notifications_enabled = true
                INNER JOIN users u ON us.user_id = u.id AND u.ntfy_topic IS NOT NULL
                WHERE (e.airstamp BETWEEN now() AND now() + interval \'24 hours\')
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
                ';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        return $resultSet->fetchAllAssociative();
    }
}
