<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\DataObjects\NotificationMessage;
use App\Entity\Notification;
use App\Exception\NotificationFailedException;
use App\Notifications\DiscordNotificationSender;
use DateTime;
use Doctrine\ORM\EntityManager;

class NotificationSendingService
{

    private int $errors = 0;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DiscordNotificationSender $discordNotificationSender,
        private readonly WebhookService $webhookService,
        private readonly Config $config
    ) {}

    public function run(): void
    {
        $this->entityManager->createQuery(
            'delete from App\Entity\Notification n where n.scheduledTime < :old'
        )
            ->setParameter('old', new DateTime('5 minutes ago'))
            ->execute();


        //todo: add types to support more notifications
        $notifications = $this->entityManager->getRepository(Notification::class)->findAll();

        foreach ($notifications as $notification) {
            if ($notification->getProcessedStatus()) {
                $this->entityManager->remove($notification);
                continue;
            }

            if ($notification->getScheduledTime() <= new DateTime()) {
                $content = $notification->getContent();
                try {
                    $this->discordNotificationSender->send(
                        new NotificationMessage(
                            $content['address'],
                            $content['title'],
                            $content['message'],
                            $content['showLink'],
                        )
                    );
                } catch (NotificationFailedException $e) {
                    $this->errors += 1;
                    error_log(
                        sprintf('%s: for notification address %s', $e->getMessage(), $content['address'])
                    );
                    continue;
                }

                $notification->setProcessedStatus(true);
                $this->entityManager->persist($notification);
            }
        }

        $this->entityManager->flush();


        if ($this->errors) {
            echo <<<RESULT
        --------------------------
        NOTIFICATION SENDING SERVICE
        --        
        ERRORS: $this->errors       
        --------------------------\n
        RESULT;

            $this->webhookService->send(
                $this->config->get('webhook_url'),
                '🚨 Notification Sending Service: An error occurred while sending notifications. Check the logs.'
            );
        }
    }
}
