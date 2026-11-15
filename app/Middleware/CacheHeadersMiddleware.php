<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;


class CacheHeadersMiddleware implements MiddlewareInterface
{
    // max-age=0 makes browsers revalidate before reusing the page: without it
    // (and with stale-while-revalidate) Firefox paints the guest copy
    // after login. The edge TTL still comes from s-maxage.
    private const PUBLIC_CACHE_CONTROL = 'public, max-age=0, s-maxage=3600';
    private const PRIVATE_CACHE_CONTROL = 'private, no-store';

    public function __construct(private readonly SessionInterface $session) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Never override a decision made further down the stack (for example
        // ValidationExceptionMiddleware sets no-store on redirects).
        if ($response->hasHeader('Cache-Control')) {
            return $response;
        }

        if ($this->session->wasStarted() || $this->session->hasSessionCookie()) {
            return $response->withHeader('Cache-Control', self::PRIVATE_CACHE_CONTROL);
        }

        if (! $this->isCacheable($request, $response)) {
            return $response;
        }

        return $response->withHeader('Cache-Control', self::PUBLIC_CACHE_CONTROL);
    }

    private function isCacheable(ServerRequestInterface $request, ResponseInterface $response): bool
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // Slim only sets the default text/html content type when the response is
        // emitted, so an empty value means "rendered HTML".
        $contentType = $response->getHeaderLine('Content-Type');

        return $contentType === '' || str_starts_with($contentType, 'text/html');
    }
}
