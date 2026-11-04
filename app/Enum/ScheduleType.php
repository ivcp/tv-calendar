<?php

declare(strict_types=1);

namespace App\Enum;

enum ScheduleType: string
{
    case User = 'user';
    case Popular = 'popular';
    case Premieres = 'premieres';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
