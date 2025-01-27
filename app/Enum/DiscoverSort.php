<?php

declare(strict_types=1);

namespace App\Enum;

use App\Contracts\SortInterface;
use BackedEnum;

enum DiscoverSort: string implements SortInterface
{
    case Popular = 'popular';
    case New = 'new';

    public static function default(): BackedEnum
    {
        return self::Popular;
    }
}
