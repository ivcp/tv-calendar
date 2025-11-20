<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

class AppNameMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Twig $twig,
        private readonly Config $config
    ) {
    }
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $this->twig->getEnvironment()->addGlobal(
            'app_name',
            $this->config->get('app_name')
        );

        $this->twig->getEnvironment()->addGlobal(
            'app_email',
            $this->config->get('mailer.from')
        );

        return $handler->handle($request);
    }
}
