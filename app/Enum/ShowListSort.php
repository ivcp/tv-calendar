<?php

declare(strict_types=1);

namespace App\Enum;

enum ShowListSort: string
{
    case Added = 'added';
    case Alphabetical = 'alphabetical';
    case Popular = 'popular';
    case New = 'new';
}
