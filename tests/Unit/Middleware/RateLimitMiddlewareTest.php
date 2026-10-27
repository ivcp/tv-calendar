<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Config;
use App\Contracts\UserInterface;
use App\DataObjects\RateLimitResult;
use App\Exception\RateLimitException;
use App\Middleware\RateLimitMiddleware;
use App\Services\RateLimiter;
use App\Services\RequestService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class RateLimitMiddlewareTest extends TestCase
{
    private const CLIENT_IP = '203.0.113.9';

    private RateLimiter&MockObject $rateLimiter;
    private RequestService&MockObject $requestService;

    protected function setUp(): void
    {
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->requestService = $this->createMock(RequestService::class);
        $this->requestService->method('getClientIp')->willReturn(self::CLIENT_IP);
    }

    public function testItPassesThroughRoutesWithoutAPolicy(): void
    {
        $this->rateLimiter->expects($this->never())->method('consume');

        $response = $this->middleware([])->process($this->request('GET', '/'), $this->handler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testItDoesNothingWhenRateLimitingIsDisabled(): void
    {
        $this->rateLimiter->expects($this->never())->method('consume');
        $policies = ['POST /login' => [['by' => 'ip', 'limit' => 40, 'window' => 900]]];

        $response = $this->middleware($policies, false)->process(
            $this->request('POST', '/login', ['email' => 'user@example.com']),
            $this->handler()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testItConsumesTheConfiguredBucketPerIp(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('consume')
            ->with(
                'POST /login:ip:900:40:' . hash('sha256', self::CLIENT_IP),
                40,
                900
            )
            ->willReturn(new RateLimitResult(true, 1, 900));

        $response = $this->middleware($this->loginPolicy())->process(
            $this->request('POST', '/login', ['email' => 'user@example.com']),
            $this->handler()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testItIgnoresATrailingSlashInThePath(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('consume')
            ->with(
                'POST /login:ip:900:40:' . hash('sha256', self::CLIENT_IP),
                40,
                900
            )
            ->willReturn(new RateLimitResult(true, 1, 900));

        $this->middleware($this->loginPolicy())->process(
            $this->request('POST', '/login/', ['email' => 'user@example.com']),
            $this->handler()
        );
    }

    public function testItNormalizesTheEmailBeforeKeyingTheBucket(): void
    {
        $policies = ['POST /forgot-password' => [['by' => 'email', 'limit' => 5, 'window' => 3600]]];

        $this->rateLimiter->expects($this->once())
            ->method('consume')
            ->with(
                'POST /forgot-password:email:3600:5:' . hash('sha256', 'user@example.com'),
                5,
                3600
            )
            ->willReturn(new RateLimitResult(true, 1, 3600));

        $this->middleware($policies)->process(
            $this->request('POST', '/forgot-password', ['email' => '  User@Example.COM ']),
            $this->handler()
        );
    }

    public function testItSkipsChecksWithoutAValueToKeyOn(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('consume')
            ->with(
                'POST /forgot-password:ip:3600:10:' . hash('sha256', self::CLIENT_IP),
                10,
                3600
            )
            ->willReturn(new RateLimitResult(true, 1, 3600));

        $response = $this->middleware($this->forgotPasswordPolicy())->process(
            $this->request('POST', '/forgot-password', []),
            $this->handler()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testItGivesEveryCheckItsOwnBucket(): void
    {
        $buckets = [];
        $this->rateLimiter->expects($this->exactly(3))
            ->method('consume')
            ->willReturnCallback(
                static function (string $bucket, int $limit, int $window) use (&$buckets): RateLimitResult {
                    $buckets[] = [$bucket, $limit, $window];

                    return new RateLimitResult(true, 1, $window);
                }
            );

        $this->middleware($this->forgotPasswordPolicy())->process(
            $this->request('POST', '/forgot-password', ['email' => 'user@example.com']),
            $this->handler()
        );

        $this->assertCount(3, $buckets);
        $this->assertNotSame($buckets[1][0], $buckets[2][0]);
        $this->assertStringContainsString(':email:3600:5:', $buckets[1][0]);
        $this->assertStringContainsString(':email:60:1:', $buckets[2][0]);
    }

    public function testItKeysUserChecksByTheUserId(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getId')->willReturn(42);

        $this->rateLimiter->expects($this->once())
            ->method('consume')
            ->with('POST /ntfy-test:user:3600:10:' . hash('sha256', '42'), 10, 3600)
            ->willReturn(new RateLimitResult(true, 1, 3600));

        $this->middleware(['POST /ntfy-test' => [['by' => 'user', 'limit' => 10, 'window' => 3600]]])
            ->process(
                $this->request('POST', '/ntfy-test')->withAttribute('user', $user),
                $this->handler()
            );
    }

    public function testItSkipsUserChecksForGuests(): void
    {
        $this->rateLimiter->expects($this->never())->method('consume');

        $response = $this->middleware(['POST /ntfy-test' => [['by' => 'user', 'limit' => 10, 'window' => 3600]]])
            ->process($this->request('POST', '/ntfy-test'), $this->handler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testItThrowsWithoutCallingTheHandlerWhenACheckIsExceeded(): void
    {
        $this->rateLimiter->method('consume')->willReturn(new RateLimitResult(false, 41, 512));

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        try {
            $this->middleware($this->loginPolicy())->process(
                $this->request('POST', '/login', ['email' => 'user@example.com']),
                $handler
            );

            $this->fail('Expected a RateLimitException to be thrown.');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertSame(512, $e->retryAfter);
            $this->assertSame(['password'], array_keys($e->errors));
        }
    }

    public function testItReportsTheFieldOfTheBlockingCheck(): void
    {
        $this->rateLimiter->method('consume')->willReturn(new RateLimitResult(false, 11, 3600));
        $policies = ['POST /register' => [['by' => 'ip', 'limit' => 10, 'window' => 3600, 'field' => 'email']]];

        $this->expectException(RateLimitException::class);

        $this->middleware($policies)->process($this->request('POST', '/register'), $this->handler());
    }

    public function testItFallsBackToAGenericErrorField(): void
    {
        $this->rateLimiter->method('consume')->willReturn(new RateLimitResult(false, 61, 60));

        try {
            $this->middleware(['GET /search' => [['by' => 'ip', 'limit' => 60, 'window' => 60]]])
                ->process($this->request('GET', '/search'), $this->handler());

            $this->fail('Expected a RateLimitException to be thrown.');
        } catch (RateLimitException $e) {
            $this->assertSame(['rate_limit'], array_keys($e->errors));
        }
    }

    private function middleware(array $policies, bool $enabled = true): RateLimitMiddleware
    {
        return new RateLimitMiddleware(
            new Config([
                'app_environment' => 'development',
                'rate_limit' => [
                    'enabled' => $enabled,
                    'policies' => $policies,
                ],
            ]),
            $this->rateLimiter,
            $this->requestService
        );
    }

    private function loginPolicy(): array
    {
        return ['POST /login' => [['by' => 'ip', 'limit' => 40, 'window' => 900, 'field' => 'password']]];
    }

    private function forgotPasswordPolicy(): array
    {
        return [
            'POST /forgot-password' => [
                ['by' => 'ip', 'limit' => 10, 'window' => 3600, 'field' => 'email'],
                ['by' => 'email', 'limit' => 5, 'window' => 3600, 'field' => 'email'],
                ['by' => 'email', 'limit' => 1, 'window' => 60, 'field' => 'email'],
            ],
        ];
    }

    private function request(string $method, string $path, ?array $body = null): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest(
            $method,
            'https://tv-calendar.test' . $path,
            ['REMOTE_ADDR' => self::CLIENT_IP]
        );

        return $body === null ? $request : $request->withParsedBody($body);
    }

    private function handler(): RequestHandlerInterface&MockObject
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response());

        return $handler;
    }
}
