<?php

declare(strict_types=1);

namespace App\Services;

use Doctrine\ORM\EntityManager;

//SendNotificationService
class NotificationScheduleService
{
    public function __construct(
        private readonly EntityManager $entityManager
    ) {
    }

    public function run(): void
    {
        //TODO:
        //get episodes
        $episodes = $this->getEpisodes();

        //foreach episode, foreach topic send notifications with scheduled time
        //mark ep as processed??

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
