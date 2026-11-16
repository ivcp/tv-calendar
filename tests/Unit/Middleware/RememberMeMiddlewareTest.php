<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Contracts\AuthInterface;
use App\Contracts\RememberMeInterface;
use App\Contracts\UserInterface;
use App\Middleware\RememberMeMiddleware;
use App\Services\RequestService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Unit\Support\RecordingSession;

final class RememberMeMiddlewareTest extends TestCase
{
    public function testItRestoresTheSessionFromAValidCookie(): void
    {
        $user = $this->user();
        $session = $this->session(false);

        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn(null);
        $auth->expects($this->once())->method('restoreSession')->with($user);

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->method('hasCookie')->willReturn(true);
        $rememberMe->expects($this->once())->method('verify')->with(false)->willReturn($user);

        $this->handle($session, $auth, $rememberMe);

        $this->assertTrue($session->started);
    }

    public function testItStartsTheSessionOnlyWhenThereIsACookie(): void
    {
        $session = $this->session(false);

        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn(null);

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->method('hasCookie')->willReturn(false);
        $rememberMe->expects($this->never())->method('verify');

        $this->handle($session, $auth, $rememberMe);

        $this->assertFalse($session->started);
    }

    public function testItSendsALapsedUserToTheLoginPage(): void
    {
        $session = $this->session(false);

        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn(null);
        $auth->expects($this->never())->method('restoreSession');

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->method('hasCookie')->willReturn(true);
        $rememberMe->expects($this->once())->method('verify')->with(false)->willReturn(null);
        $rememberMe->expects($this->once())->method('clear');

        $response = $this->handle($session, $auth, $rememberMe);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
        $this->assertSame(
            ['Your login has expired. Please log in again.'],
            $session->getFlashed('notice')
        );
    }

    public function testItLeavesTheCookieAloneOnXhrRequests(): void
    {
        $session = $this->session(false);

        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn(null);

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->method('hasCookie')->willReturn(true);
        $rememberMe->method('verify')->willReturn(null);
        // A redirect would hand HTML to fetch(); the next page load redirects.
        $rememberMe->expects($this->never())->method('clear');

        $response = $this->handle(
            $session,
            $auth,
            $rememberMe,
            'GET',
            '/',
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testItNeverRedirectsAPostBecauseThatWouldBreakLogin(): void
    {
        $session = $this->session(false);

        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn(null);

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->method('hasCookie')->willReturn(true);
        $rememberMe->method('verify')->willReturn(null);
        $rememberMe->expects($this->never())->method('clear');

        $response = $this->handle($session, $auth, $rememberMe, 'POST', '/login');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testItDoesNotRedirectTheLoginPageToItself(): void
    {
        $session = $this->session(false);

        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn(null);

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->method('hasCookie')->willReturn(true);
        $rememberMe->method('verify')->willReturn(null);
        $rememberMe->expects($this->never())->method('clear');

        $response = $this->handle($session, $auth, $rememberMe, 'GET', '/login');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testItDoesNotRedirectLinksOpenedFromEmail(): void
    {
        $session = $this->session(false);

        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn(null);

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->method('hasCookie')->willReturn(true);
        $rememberMe->method('verify')->willReturn(null);
        $rememberMe->expects($this->never())->method('clear');

        $response = $this->handle($session, $auth, $rememberMe, 'GET', '/verify/7/abc123');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testItKeepsAnExistingRememberedSession(): void
    {
        $session = $this->session(true);
        $session->put('user', 7);
        $session->put('_remember', true);

        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn($this->user());

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->never())->method('issue');
        $rememberMe->expects($this->never())->method('verify');

        $this->handle($session, $auth, $rememberMe);

        $this->assertTrue($session->get('_remember'));
    }

    public function testItUpgradesASessionThatPredatesRememberMe(): void
    {
        $user = $this->user();
        $session = $this->session(true);
        $session->put('user', 7);

        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn($user);

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->once())->method('issue')->with($user);
        $rememberMe->method('hasCookie')->willReturn(true);

        $this->handle($session, $auth, $rememberMe);

        $this->assertTrue($session->get('_remember'));
    }

    public function testItDoesNotUpgradeAnExplicitlyUncheckedLogin(): void
    {
        $session = $this->session(true);
        $session->put('user', 7);
        $session->put('_remember', false);

        $auth = $this->createMock(AuthInterface::class);
        $auth->method('user')->willReturn($this->user());

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->never())->method('issue');

        $this->handle($session, $auth, $rememberMe);

        $this->assertFalse($session->get('_remember'));
    }

    /**
     * @param array<string, string> $headers
     */
    private function handle(
        RecordingSession $session,
        AuthInterface $auth,
        RememberMeInterface $rememberMe,
        string $method = 'GET',
        string $path = '/',
        array $headers = []
    ): ResponseInterface {
        $middleware = new RememberMeMiddleware(
            $auth,
            $rememberMe,
            $session,
            new RequestService($session),
            new ResponseFactory()
        );

        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://example.test' . $path);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $middleware->process($request, $this->handler());
    }

    /**
     * The in-memory session is always "active"; this variant tracks whether the
     * middleware actually started it, and keeps flashed notices.
     */
    private function session(bool $started): RecordingSession
    {
        return new class ($started) extends RecordingSession {
            public bool $started;

            public function __construct(bool $started)
            {
                $this->started = $started;
            }

            public function start(): void
            {
                $this->started = true;
            }

            public function isActive(): bool
            {
                return $this->started;
            }
        };
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

    private function user(): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn(7);

        return $user;
    }
}
