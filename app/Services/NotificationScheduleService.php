<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\DataObjects\NotificationMessage;
use App\Entity\User;
use App\Enum\NotificationTime;
use App\Notifications\DiscordNotificationScheduler;
use App\Notifications\NotificationScheduler;
use App\Notifications\NtfyNotificationScheduler;
use Doctrine\ORM\EntityManager;

class NotificationScheduleService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly NtfyService $ntfyService,
        private readonly Config $config,
        private readonly WebhookService $webhookService,
        private readonly NotificationScheduler $notificationScheduler
    ) {}

    public function run(): void
    {
        $episodes = $this->getEpisodes();

        $episodesTotal = count($episodes);

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
            $users = json_decode($episode['users']);

            if (is_array($users)) {
                foreach ($users as $user) {
                    $notificationTime = $user->notification_time;

                    $timestamp = match ($notificationTime) {
                        NotificationTime::AIRTIME->value => strtotime($episode['airstamp']),
                        NotificationTime::ONE_HOUR_BEFORE->value => strtotime($episode['airstamp']) - 3600,
                        NotificationTime::ONE_HOUR_AFTER->value => strtotime($episode['airstamp']) + 3600,
                    };

                    $availableString =  match ($notificationTime) {
                        NotificationTime::AIRTIME->value => 'Airing now',
                        NotificationTime::ONE_HOUR_BEFORE->value => 'Airing in one hour',
                        NotificationTime::ONE_HOUR_AFTER->value => 'Aired one hour ago',
                    };

                    $messageWithAiring = "$availableString\n\n" . $message;

                    if ($user->discord_webhook_url) {
                        $this->notificationScheduler->attach(
                            new DiscordNotificationScheduler(
                                $this->entityManager,
                                new NotificationMessage(
                                    $user->discord_webhook_url,
                                    $title,
                                    $messageWithAiring,
                                    $showLink
                                ),
                                $timestamp
                            )
                        );
                    }

                    if ($user->ntfy_topic) {

                        $this->notificationScheduler->attach(
                            new NtfyNotificationScheduler(
                                $this->ntfyService,
                                new NotificationMessage(
                                    $user->ntfy_topic,
                                    $title,
                                    $messageWithAiring,
                                    $showLink
                                ),
                                $timestamp,
                            )

                        );
                    }
                }
            }
        }

        $this->notificationScheduler->notify();
        $messagesQueued = $this->notificationScheduler->getMessagesQueued();
        $errorsSchedulingMessage  = $this->notificationScheduler->getErrorsSchedulingMessage();


        echo <<<RESULT
        --------------------------
        NOTIFICATION SCHEDULE SERVICE
        --
        EPISODES TOTAL: $episodesTotal
        MESSAGES QUEUED:  $messagesQueued
        ERRORS: $errorsSchedulingMessage       
        --------------------------\n
        RESULT;

        if ($errorsSchedulingMessage) {
            $this->webhookService->send(
                $this->config->get('webhook_url'),
                "🚨 Notification Schedule Service: $errorsSchedulingMessage error(s)" .
                    " occurred while sending notifications. Check the logs."
            );
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
                JSON_AGG(DISTINCT u) AS "users"
                FROM episodes e
                INNER JOIN shows s ON s.id = e.show_id
                INNER JOIN users_shows us ON us.show_id = s.id AND us.notifications_enabled = true
                INNER JOIN users u ON us.user_id = u.id AND (u.ntfy_topic IS NOT NULL OR u.discord_webhook_url IS NOT NULL)
                WHERE e.airstamp IS NOT NULL AND (e.airstamp BETWEEN now() + interval \'2 hours\' AND now() + interval \'3 hours\')
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


    /**
     * only to be run if ntfy server restored from backup
     *     
     **/
    public function checkAndSync(): void
    {

        $actions = 0;
        $ntfyUsers = $this->ntfyService->getAllUsers();

        foreach ($ntfyUsers as $ntfyUser) {
            if ($ntfyUser['role'] === 'admin' || $ntfyUser['role'] === 'anonymous') {
                continue;
            }
            $user = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $ntfyUser['username']]);
            if (!$user) {
                $this->ntfyService->deleteUser($ntfyUser['username']);
                $actions += 1;
                continue;
            }
            if ($user->getNtfyTopic() !== $ntfyUser['grants'][0]['topic']) {
                $user->setNtfyTopic(null);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->ntfyService->deleteUser($ntfyUser['username']);
                $actions += 1;
            }
        }

        $q = $this->entityManager->createQuery(
            'SELECT u FROM App\Entity\User u WHERE u.ntfyTopic IS NOT NULL'
        );
        $users = $q->getResult();

        foreach ($users as $user) {
            $exists = array_find($ntfyUsers, fn($nu) => $nu['username'] === $user->getEmail());
            if (!$exists) {
                $user->setNtfyTopic(null);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $actions += 1;
            }
        }

        if ($actions) {
            echo "Actions taken for $actions user(s)." . PHP_EOL;
            return;
        }

        echo "All good! No intervention required." . PHP_EOL;
    }
}
