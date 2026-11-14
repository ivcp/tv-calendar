<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\SessionInterface;
use App\Services\RequestService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class StartSessionsMiddleware implements MiddlewareInterface
{
    /**
     * GET routes that render a form carrying a CSRF token, and therefore need a
     * session before the form is rendered. Everything else starts its session
     * lazily on the first write, which keeps anonymous page views cacheable.
     */
    private const SESSION_PATHS = [
        '/login',
        '/register',
        '/forgot-password',
    ];

    private const SESSION_PATH_PREFIXES = [
        '/reset-password/',
    ];

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly SessionInterface $session,
        private readonly RequestService $requestService
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->shouldStart($request)) {
            $this->session->start();
        }

        $response = $handler->handle($request);

        if ($this->session->isActive()) {
            if ($request->getMethod() === 'GET' && ! $this->requestService->isXhr($request)) {
                $this->session->put('previousUrl', (string) $request->getUri());
            }

            $this->session->save();
        }

        return $response;
    }

    private function shouldStart(ServerRequestInterface $request): bool
    {
        if ($this->session->isActive()) {
            return false;
        }

        if ($this->session->hasSessionCookie()) {
            return true;
        }

        if (! in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return true;
        }

        $path = $request->getUri()->getPath();

        if (in_array($path, self::SESSION_PATHS, true)) {
            return true;
        }

        foreach (self::SESSION_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}