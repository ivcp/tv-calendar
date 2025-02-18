<?php

declare(strict_types=1);

namespace App\Contracts;

use BackedEnum;

interface SortInterface
{
    public static function default(): BackedEnum;
}
