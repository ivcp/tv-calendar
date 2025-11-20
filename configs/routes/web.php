<?php

declare(strict_types=1);

use App\Controllers\AboutController;
use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\PasswordResetController;
use App\Controllers\ProfileController;
use App\Controllers\ShowController;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\ValidateSignatureMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->get('/', [CalendarController::class, 'index']);
    $app->get(
        '/{year:\b19[2-9]\d|20[0-4]\d|2050\b}-{month:\b0[1-9]|1[0-2]\b}',
        [CalendarController::class, 'getMonth']
    );

    $app->get('/discover', [ShowController::class, 'discover']);

    $app->get('/shows/{showId:[0-9]+}', [ShowController::class, 'get']);
    $app->get('/shows/{showId:[0-9]+}/image', [ShowController::class, 'serveOptimizedShowImage']);

    $app->get('/search', [ShowController::class, 'search']);


    $app->get('/showlist', [ShowController::class, 'index']);
    $app->group('/showlist', function (RouteCollectorProxy $showlist) {
        $showlist->post('', [ShowController::class, 'store']);
        $showlist->delete('/{showId}', [ShowController::class, 'delete']);
    })->add(AuthMiddleware::class);

    $app->group('', function (RouteCollectorProxy $guest) {
        $guest->get('/login', [AuthController::class, 'loginView']);
        $guest->get('/register', [AuthController::class, 'registerView']);
        $guest->post('/login', [AuthController::class, 'login']);
        $guest->post('/register', [AuthController::class, 'register']);
    })->add(GuestMiddleware::class);

    $app->group('', function (RouteCollectorProxy $user) {
        $user->get('/verify/{id}/{hash}', [AuthController::class, 'verify'])
        ->setName('verify')
        ->add(ValidateSignatureMiddleware::class);
        $user->post('/verify', [AuthController::class, 'resendEmail']);

        $user->post('/logout', [AuthController::class, 'logout']);
        $user->get('/update-password', [PasswordResetController::class, 'updatePasswordView']);
        $user->post('/update-password', [PasswordResetController::class, 'updatePassword']);
        $user->get('/profile', [ProfileController::class, 'index']);
        $user->patch('/profile', [ProfileController::class, 'setStartOfWeek']);
        $user->delete('/profile', [ProfileController::class, 'delete']);
    })->add(AuthMiddleware::class);

    $app->get('/forgot-password', [PasswordResetController::class, 'forgotPasswordView']);
    $app->post('/forgot-password', [PasswordResetController::class, 'handleForgotPassword']);
    $app->get('/reset-password/{token}', [PasswordResetController::class, 'resetPasswordView'])
    ->setName('password-reset')
    ->add(ValidateSignatureMiddleware::class);
    $app->post('/reset-password/{token}', [PasswordResetController::class, 'resetPassword']);


    $app->get('/google-oauth', [AuthController::class, 'googleOauth']);

    $app->get('/about', [AboutController::class, 'index']);
};
