<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SessionInterface;
use App\Middleware\StartSessionsMiddleware;
use App\Services\RequestService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\ServerRequestFactory;

class StartSessionsMiddlewareTest extends TestCase
{
    public function testItDoesNotStartASessionForAnAnonymousPageView(): void
    {
        $session = $this->session();
        $session->expects($this->never())->method('start');

        $this->dispatch('GET', '/discover', $session);
    }

    public function testItDoesNotStartASessionForTheVerificationPage(): void
    {
        $session = $this->session();
        $session->expects($this->never())->method('start');

        $this->dispatch('GET', '/verify/1/abc', $session);
    }

    public function testItStartsASessionForAFormPage(): void
    {
        $session = $this->session();
        $session->expects($this->once())->method('start');

        $this->dispatch('GET', '/login', $session);
    }

    public function testItStartsASessionForPasswordResetLinks(): void
    {
        $session = $this->session();
        $session->expects($this->once())->method('start');

        $this->dispatch('GET', '/reset-password/token', $session);
    }

    public function testItStartsASessionForUnsafeMethods(): void
    {
        $session = $this->session();
        $session->expects($this->once())->method('start');

        $this->dispatch('POST', '/showlist', $session);
    }

    public function testItStartsASessionWhenTheRequestCarriesASessionCookie(): void
    {
        $session = $this->session();
        $session->method('hasSessionCookie')->willReturn(true);
        $session->expects($this->once())->method('start');

        $this->dispatch('GET', '/discover', $session);
    }

    public function testItRecordsThePreviousUrlAndClosesAnActiveSession(): void
    {
        $session = $this->session();
        $session->method('isActive')->willReturn(true);
        $session->expects($this->never())->method('start');
        $session->expects($this->once())
            ->method('put')
            ->with('previousUrl', 'https://example.test/discover');
        $session->expects($this->once())->method('save');

        $this->dispatch('GET', '/discover', $session);
    }

    private function dispatch(string $method, string $path, SessionInterface $session): ResponseInterface
    {
        $middleware = new StartSessionsMiddleware($session, new RequestService($session));

        return $middleware->process($this->request($method, $path), $this->handler());
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, 'https://example.test' . $path);
    }

    private function session(): SessionInterface
    {
        return $this->createMock(SessionInterface::class);
    }

    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
    }
}
