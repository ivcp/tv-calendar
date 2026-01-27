<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\NotificationMessage;

interface NotificationSenderInterface
{
    /**
     *   
     * @throws NotificationFailedException
     **/
    public function send(NotificationMessage $content): void;
}
