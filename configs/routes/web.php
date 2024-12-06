<?php

declare(strict_types=1);

use App\Controllers\CalendarController;
use Slim\App;

return function (App $app) {
    $app->get('/', [CalendarController::class, 'index']);
    $app->get('/{year:\b[0-9]{4}\b}-{month:\b[0-9]{2}\b}', [CalendarController::class, 'getMonth']);
};
