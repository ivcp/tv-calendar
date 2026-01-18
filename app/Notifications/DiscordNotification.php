<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\NotificationMessage;
use App\Entity\Notification;
use App\Exception\NotificationFailedException;
use DateTime;
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Client;

class DiscordNotification implements NotificationInterface
{

    public function __construct(
        private Client $client,
        private readonly EntityManager $entityManager,

    ) {}

    public function queue(NotificationMessage $content, DateTime $scheduledTime): void
    {
        $notification = new Notification();
        $notification->setContent($content);
        $notification->setScheduledTime($scheduledTime);
        $notification->setStatus(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function send(NotificationMessage $content): void
    {
        $message = <<<MESSAGE
        $content->title
        $content->message 
        $content->showLink
        MESSAGE;

        $response = $this->client->post($content->address, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['content' => $message])
        ]);

        if ($response->getStatusCode() !== 204) {
            throw new NotificationFailedException('Failed sending discord webhook: ' . $response->getReasonPhrase());
        }
    }
}
