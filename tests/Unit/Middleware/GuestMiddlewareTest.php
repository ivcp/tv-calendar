<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Contracts\AuthInterface;
use App\Contracts\UserInterface;
use App\Middleware\GuestMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class GuestMiddlewareTest extends TestCase
{
    public function testItLetsAStaleSessionReachTheLoginPage(): void
    {
        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn(null);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn(new Response());

        $response = (new GuestMiddleware(new ResponseFactory(), $auth))->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/login'),
            $handler
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testItRedirectsLoggedInUsersAwayFromGuestRoutes(): void
    {
        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn($this->createMock(UserInterface::class));

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = (new GuestMiddleware(new ResponseFactory(), $auth))->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/login'),
            $handler
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/', $response->getHeaderLine('Location'));
    }
}
