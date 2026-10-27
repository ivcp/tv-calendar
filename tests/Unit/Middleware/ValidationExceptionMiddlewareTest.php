<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Config;
use App\Exception\RateLimitException;
use App\Exception\ValidationException;
use App\Middleware\ValidationExceptionMiddleware;
use App\ResponseFormatter;
use App\Services\RequestService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Unit\Support\RecordingSession;
use Throwable;

class ValidationExceptionMiddlewareTest extends TestCase
{
    private RecordingSession $session;
    private ValidationExceptionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->session = new RecordingSession();
        $this->middleware = new ValidationExceptionMiddleware(
            new ResponseFactory(),
            $this->session,
            new RequestService($this->session),
            new ResponseFormatter(new Config(['json_tags' => JSON_THROW_ON_ERROR]))
        );
    }

    public function testItReturnsA429JsonResponseForXhrRequests(): void
    {
        $response = $this->middleware->process(
            $this->request()->withHeader('X-Requested-With', 'XMLHttpRequest'),
            $this->handler(new RateLimitException('password', 900))
        );

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('900', $response->getHeaderLine('Retry-After'));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            '{"msg":"Too many requests. Please try again in 15 minutes."}',
            (string) $response->getBody()
        );
    }

    public function testItFlashesAndRedirectsRateLimitedFormRequests(): void
    {
        $response = $this->middleware->process(
            $this->request(),
            $this->handler(new RateLimitException('password', 60))
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://tv-calendar.test/login', $response->getHeaderLine('Location'));
        $this->assertSame(
            ['Too many requests. Please try again in 1 minute.'],
            $this->session->getFlashed('errors')['password']
        );
        $this->assertSame('user@example.com', $this->session->getFlashed('old')['email']);
        $this->assertArrayNotHasKey('password', $this->session->getFlashed('old'));
        $this->assertArrayNotHasKey('confirm_password', $this->session->getFlashed('old'));
    }

    public function testItRedirectsToTheLastVisitedPageWhenThereIsNoReferer(): void
    {
        $this->session->put('previousUrl', '/login');

        $response = $this->middleware->process(
            (new ServerRequestFactory())->createServerRequest(
                'POST',
                'https://tv-calendar.test/login'
            )->withParsedBody(['email' => 'user@example.com']),
            $this->handler(new RateLimitException('email', 60))
        );

        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testItStillReturns422ForValidationErrors(): void
    {
        $response = $this->middleware->process(
            $this->request()->withHeader('X-Requested-With', 'XMLHttpRequest'),
            $this->handler(new ValidationException(['email' => ['Invalid email.']]))
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            '{"errors":{"email":["Invalid email."]}}',
            (string) $response->getBody()
        );
    }

    public function testItStillFlashesAndRedirectsInvalidForms(): void
    {
        $response = $this->middleware->process(
            $this->request(),
            $this->handler(new ValidationException(['email' => ['Invalid email.']]))
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://tv-calendar.test/login', $response->getHeaderLine('Location'));
        $this->assertSame(['Invalid email.'], $this->session->getFlashed('errors')['email']);
        $this->assertSame('user@example.com', $this->session->getFlashed('old')['email']);
        $this->assertArrayNotHasKey('password', $this->session->getFlashed('old'));
    }

    private function request(): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://tv-calendar.test/login')
            ->withHeader('Referer', 'https://tv-calendar.test/login')
            ->withParsedBody([
                'email' => 'user@example.com',
                'password' => 'secret',
                'confirm_password' => 'secret',
            ]);
    }

    private function handler(Throwable $exception): RequestHandlerInterface
    {
        return new class ($exception) implements RequestHandlerInterface {
            public function __construct(private readonly Throwable $exception)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };
    }
}
