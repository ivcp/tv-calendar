<?php

declare(strict_types=1);

use App\Controllers\CalendarController;
use Slim\App;

return function (App $app) {
    $app->get('/', [CalendarController::class, 'index']);
    $app->get('/{year:\b[0-9]{4}\b}-{month:\b0[1-9]|1[0-2]\b}', [CalendarController::class, 'getMonth']);
};
