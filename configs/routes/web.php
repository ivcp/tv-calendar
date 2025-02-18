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

    $app->get('/shows/{showId:[0-9]+}', [ShowController::class, 'get']);
    $app->get('/shows/{showId:[0-9]+}/image', [ShowController::class, 'serveOptimizedShowImage']);

    $app->get('/search', [ShowController::class, 'search']);


    $app->group('/showlist', function (RouteCollectorProxy $showlist) {
        $showlist->get('', [ShowController::class, 'index']);
        $showlist->post('', [ShowController::class, 'store']);
        $showlist->delete('/{showId}', [ShowController::class, 'delete']);
    })->add(AuthMiddleware::class);

    $app->group('', function (RouteCollectorProxy $guest) {
        $guest->get('/login', [AuthController::class, 'loginView']);
        $guest->get('/register', [AuthController::class, 'registerView']);
        $guest->post('/login', [AuthController::class, 'login']);
        $guest->post('/register', [AuthController::class, 'register']);
    })->add(GuestMiddleware::class);
    $app->post('/logout', [AuthController::class, 'logout'])->add(AuthMiddleware::class);
};
