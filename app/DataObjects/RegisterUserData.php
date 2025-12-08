<?php

declare(strict_types=1);

namespace App\DataObjects;

use App\Enum\NotificationTime;

class RegisterUserData
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $password = null,
        public readonly ?bool $verified = false,
        public readonly bool $startOfWeekSunday = false,
        public readonly ?string $ntfyTopic = null,
        public readonly NotificationTime $notificationTime = NotificationTime::AIRTIME
    ) {
    }
}
