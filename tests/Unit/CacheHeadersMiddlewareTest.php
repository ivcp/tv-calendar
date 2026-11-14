<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SessionInterface;
use App\Middleware\CacheHeadersMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\ServerRequestFactory;

class CacheHeadersMiddlewareTest extends TestCase
{
    public function testItMarksAnonymousHtmlAsPublic(): void
    {
        $response = $this->handle('GET', '/', $this->session(false, false), 'text/html; charset=UTF-8');

        $this->assertStringContainsString('public', $response->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('s-maxage=3600', $response->getHeaderLine('Cache-Control'));
    }

    public function testItMarksAResponseFromAStartedSessionAsPrivate(): void
    {
        $response = $this->handle('GET', '/login', $this->session(true, false), 'text/html; charset=UTF-8');

        $this->assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testItMarksAResponseFromARequestWithASessionCookieAsPrivate(): void
    {
        $response = $this->handle('GET', '/', $this->session(false, true), 'text/html; charset=UTF-8');

        $this->assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testItLeavesNonHtmlResponsesAlone(): void
    {
        $response = $this->handle('GET', '/2026-11', $this->session(false, false), 'application/json');

        $this->assertFalse($response->hasHeader('Cache-Control'));
    }

    public function testItTreatsAResponseWithoutAContentTypeAsHtml(): void
    {
        $response = $this->handle('GET', '/', $this->session(false, false), null);

        $this->assertStringContainsString('public', $response->getHeaderLine('Cache-Control'));
    }

    public function testItLeavesUnsafeMethodsAlone(): void
    {
        $response = $this->handle('POST', '/login', $this->session(false, false), 'text/html; charset=UTF-8');

        $this->assertFalse($response->hasHeader('Cache-Control'));
    }

    public function testItDoesNotOverrideAnExistingCacheControlHeader(): void
    {
        $existing = (new Response())->withHeader('Content-Type', 'text/html')->withHeader('Cache-Control', 'no-store');

        $response = $this->handle('GET', '/', $this->session(false, false), null, $existing);

        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    private function handle(
        string $method,
        string $path,
        SessionInterface $session,
        ?string $contentType,
        ?ResponseInterface $response = null
    ): ResponseInterface {
        $response ??= new Response();

        if ($contentType !== null) {
            $response = $response->withHeader('Content-Type', $contentType);
        }

        $middleware = new CacheHeadersMiddleware($session);

        return $middleware->process($this->request($method, $path), $this->handler($response));
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, 'https://example.test' . $path);
    }

    private function session(bool $wasStarted, bool $hasCookie): SessionInterface
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('wasStarted')->willReturn($wasStarted);
        $session->method('hasSessionCookie')->willReturn($hasCookie);

        return $session;
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new class ($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}