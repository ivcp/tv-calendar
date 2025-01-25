<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\ShowController;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->get('/', [CalendarController::class, 'index']);
    $app->get('/{year:\b[0-9]{4}\b}-{month:\b0[1-9]|1[0-2]\b}', [CalendarController::class, 'getMonth']);

    $app->get('/discover', [ShowController::class, 'discover']);

    $app->post('/showlist', [ShowController::class, 'store']);
    $app->delete('/showlist/{id}', [ShowController::class, 'delete']);


    $app->group('', function (RouteCollectorProxy $guest) {
        $guest->get('/login', [AuthController::class, 'loginView']);
        $guest->get('/register', [AuthController::class, 'registerView']);
        $guest->post('/login', [AuthController::class, 'login']);
        $guest->post('/register', [AuthController::class, 'register']);

    })->add(GuestMiddleware::class);
    $app->post('/logout', [AuthController::class, 'logout'])->add(AuthMiddleware::class);
};
