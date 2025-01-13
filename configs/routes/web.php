<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use Slim\App;

return function (App $app) {
    $app->get('/', [CalendarController::class, 'index']);
    $app->get('/{year:\b[0-9]{4}\b}-{month:\b0[1-9]|1[0-2]\b}', [CalendarController::class, 'getMonth']);

    $app->get('/login', [AuthController::class, 'loginView'])->add(GuestMiddleware::class);
    $app->get('/register', [AuthController::class, 'registerView'])->add(GuestMiddleware::class);
    $app->post('/login', [AuthController::class, 'login'])->add(GuestMiddleware::class);
    $app->post('/register', [AuthController::class, 'register'])->add(GuestMiddleware::class);

    $app->post('/logout', [AuthController::class, 'logout'])->add(AuthMiddleware::class);
};
