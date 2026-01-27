<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\NotificationMessage;
use App\Services\NtfyService;
use DateTime;
use RuntimeException;
use SplObserver;
use SplSubject;

class NtfyNotificationScheduler implements SplObserver
{

    public function __construct(
        private readonly NtfyService $ntfyService,
        private readonly NotificationMessage $content,
        private readonly int $scheduledTime
    ) {}

    public function update(
        SplSubject $subject
    ): void {

        try {
            $this->ntfyService->sendNotification(
                $this->content->address,
                $this->content->title,
                $this->content->message,
                $this->scheduledTime,
                $this->content->showLink
            );
            /** @var NotificationScheduler $subject */
            $subject->incrementMessagesCount();
        } catch (RuntimeException $e) {
            /** @var NotificationScheduler $subject */
            $subject->incrementErrorCount();
            error_log("ERROR sending notification: " . $e->getMessage());
        }
    }
}
