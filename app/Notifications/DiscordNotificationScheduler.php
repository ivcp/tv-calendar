<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\NotificationMessage;
use App\Entity\Notification;
use DateTime;
use Doctrine\ORM\EntityManager;
use SplObserver;
use SplSubject;

class DiscordNotificationScheduler implements SplObserver
{

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly NotificationMessage $content,
        private readonly int $scheduledTime
    ) {}

    public function update(
        SplSubject $subject
    ): void {
        $date = new DateTime();
        $date->setTimestamp($this->scheduledTime);

        $notification = new Notification();
        $notification->setContent($this->content);
        $notification->setScheduledTime($date);
        $notification->setProcessedStatus(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        /** @var NotificationScheduler $subject */
        $subject->incrementMessagesCount();
    }
}
