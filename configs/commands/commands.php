<?php

declare(strict_types=1);

use App\Command\LoadFixtures;
use App\Command\SeedDB;

return [
    LoadFixtures::class,
    SeedDB::class
];
