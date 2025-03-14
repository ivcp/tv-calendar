<?php

declare(strict_types=1);

use App\Config;
use App\Middleware\BadRequestExceptionMiddleware;
use App\Middleware\CsrfFieldsMiddleware;
use App\Middleware\NotFoundExceptionMiddleware;
use App\Middleware\OldFormDataMiddleware;
use App\Middleware\StartSessionsMiddleware;
use App\Middleware\UserMiddleware;
use App\Middleware\ValidationErrorsMiddleware;
use App\Middleware\ValidationExceptionMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app) {
    $container = $app->getContainer();
    $config    = $container->get(Config::class);
    $twig = $container->get(Twig::class);

    $app->add(UserMiddleware::class);
    $app->add(MethodOverrideMiddleware::class);
    $app->add(CsrfFieldsMiddleware::class);
    $app->add('csrf');
    $app->add(TwigMiddleware::create($app, $twig));
    $app->add(NotFoundExceptionMiddleware::class);
    $app->add(BadRequestExceptionMiddleware::class);
    $app->add(ValidationExceptionMiddleware::class);
    $app->add(ValidationErrorsMiddleware::class);
    $app->add(OldFormDataMiddleware::class);
    $app->add(StartSessionsMiddleware::class);
    $app->addBodyParsingMiddleware();

    $app->addErrorMiddleware(
        (bool) $config->get('display_error_details'),
        (bool) $config->get('log_errors'),
        (bool) $config->get('log_error_details')
    )->setErrorHandler(
        HttpNotFoundException::class,
        function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) use ($twig) {
            $response = new Response();
            return $twig->render($response->withStatus(404), 'error/404.twig');
        }
    )->setErrorHandler(
        HttpBadRequestException::class,
        function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) use ($twig) {
            $response = new Response();
            return $twig->render($response->withStatus(400), 'error/400.twig');
        }
    );
};
