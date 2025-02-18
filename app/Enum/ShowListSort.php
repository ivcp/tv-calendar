<?php

declare(strict_types=1);

namespace App\Enum;

use App\Contracts\SortInterface;
use BackedEnum;

enum ShowListSort: string implements SortInterface
{
    case Added = 'added';
    case Alphabetical = 'alphabetical';
    case Popular = 'popular';
    case New = 'new';

    public static function default(): BackedEnum
    {
        return self::Added;
    }
}
