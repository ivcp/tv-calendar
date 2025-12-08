<?php

namespace App\Enum;

enum NotificationTime: string
{
    case AIRTIME = 'airtime';
    case ONE_HOUR_BEFORE = 'one_hour_before';
    case ONE_HOUR_AFTER = 'one_hour_after';
}
