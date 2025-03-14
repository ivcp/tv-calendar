<?php

declare(strict_types=1);

use App\Command\GenerateAppKey;
use App\Command\SeedDB;

return [
    SeedDB::class,
    GenerateAppKey::class
];
