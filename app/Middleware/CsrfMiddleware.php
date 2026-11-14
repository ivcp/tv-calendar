<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\SessionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Runs Slim's CSRF guard only when the request has a session.
 *
 * The guard stores its token in the session and throws when it is instantiated
 * without one, so anonymous requests that never start a session must skip it.
 * They cannot mutate state anyway: every unsafe method starts a session in
 * StartSessionsMiddleware.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionInterface $session,
        private readonly ContainerInterface $container
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->session->isActive()) {
            return $handler->handle($request);
        }

        return $this->container->get('csrf')->process($request, $handler);
    }
}