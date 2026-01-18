<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\NotificationMessage;
use DateTime;

interface NotificationInterface
{
    public function queue(NotificationMessage $content, DateTime $scheduledTime): void;

    /**
     *   
     * @throws NotificationFailedException
     **/
    public function send(NotificationMessage $content): void;
}
