<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Config;
use App\Controllers\AuthController;
use App\Contracts\AuthInterface;
use App\Contracts\RequestValidatorFactoryInterface;
use App\Contracts\RequestValidatorInterface;
use App\Contracts\UserProviderServiceInterface;
use App\Entity\User;
use App\Mail\VerificatonEmail;
use App\ResponseFormatter;
use App\Services\UserShowsService;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\Unit\Support\InMemorySession;

class AuthControllerTest extends TestCase
{
    private InMemorySession $session;
    private AuthInterface&MockObject $auth;
    private UserProviderServiceInterface&MockObject $userProvider;
    private UserShowsService&MockObject $userShowsService;
    private array $history = [];

    protected function setUp(): void
    {
        $this->session = new InMemorySession();
        $this->auth = $this->createMock(AuthInterface::class);
        $this->userProvider = $this->createMock(UserProviderServiceInterface::class);
        $this->userShowsService = $this->createMock(UserShowsService::class);
    }

    public function testRedirectsToGoogleWithARandomStateStoredInTheSession(): void
    {
        $controller = $this->makeController();

        $response = $controller->googleOauth(
            $this->request(['shows' => ['1', '2']]),
            new Response()
        );

        $query = $this->locationQuery($response);
        $state = $query['state'] ?? '';

        $this->assertSame(302, $response->getStatusCode());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $state);
        $this->assertSame($state, $this->session->get('oauth.google.state'));

        // The locally stored show IDs must not be smuggled through the state.
        $this->assertArrayNotHasKey('shows', $query);

        $showsPayload = $this->session->get('oauth.google.shows');
        $this->assertIsString($showsPayload);
        $this->assertStringContainsString('.', $showsPayload);
    }

    public function testRejectsACallbackWhoseStateWasNotIssuedToThisSession(): void
    {
        $controller = $this->makeController();

        $this->auth->expects($this->never())->method('login');
        $this->userProvider->expects($this->never())->method('getByCredentials');

        $response = $controller->googleOauth(
            $this->request(['code' => 'attacker-code', 'state' => 'attacker-state']),
            new Response()
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));

        // No code exchange or userinfo request may happen before the state check.
        $this->assertCount(0, $this->history);
    }

    public function testRejectsACallbackWhenTheStateIsMissing(): void
    {
        $controller = $this->makeController();

        $this->auth->expects($this->never())->method('login');

        $response = $controller->googleOauth(
            $this->request(['code' => 'attacker-code']),
            new Response()
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
        $this->assertCount(0, $this->history);
    }

    public function testLogsInAGoogleUserWhenTheStateMatchesAndTheEmailIsVerified(): void
    {
        $controller = $this->makeController([
            new GuzzleResponse(200, [], json_encode(['access_token' => 'access-token'])),
            new GuzzleResponse(
                200,
                [],
                json_encode(['email' => 'user@example.com', 'email_verified' => true])
            ),
        ]);

        $response = $controller->googleOauth($this->request(['shows' => ['12']]), new Response());
        $state = $this->locationQuery($response)['state'];

        $user = new User();
        $user->setVerifiedAt(new DateTime());

        $this->userProvider
            ->method('getByCredentials')
            ->with(['email' => 'user@example.com'])
            ->willReturn($user);

        $this->auth
            ->expects($this->once())
            ->method('login')
            ->with($user);

        $this->userShowsService
            ->expects($this->once())
            ->method('addMultipleShows')
            ->with([12], $user);

        $callbackResponse = $controller->googleOauth(
            $this->request(['code' => 'authorization-code', 'state' => $state]),
            new Response()
        );

        $this->assertSame(302, $callbackResponse->getStatusCode());
        $this->assertSame('/', $callbackResponse->getHeaderLine('Location'));
        $this->assertCount(2, $this->history);

        $tokenRequest = $this->history[0]['request'];
        $this->assertSame('POST', $tokenRequest->getMethod());
        $this->assertStringContainsString('client_secret=client-secret', (string) $tokenRequest->getBody());

        // Both one-time values are consumed by the callback.
        $this->assertNull($this->session->get('oauth.google.state'));
        $this->assertNull($this->session->get('oauth.google.shows'));
    }

    public function testRejectsAGoogleAccountWithAnUnverifiedEmail(): void
    {
        $controller = $this->makeController([
            new GuzzleResponse(200, [], json_encode(['access_token' => 'access-token'])),
            new GuzzleResponse(
                200,
                [],
                json_encode(['email' => 'user@example.com', 'email_verified' => false])
            ),
        ]);

        $response = $controller->googleOauth($this->request([]), new Response());
        $state = $this->locationQuery($response)['state'];

        $callbackResponse = $controller->googleOauth(
            $this->request(['code' => 'authorization-code', 'state' => $state]),
            new Response()
        );

        $this->assertSame(302, $callbackResponse->getStatusCode());
        $this->assertSame('/login', $callbackResponse->getHeaderLine('Location'));
        $this->auth->expects($this->never())->method('login');
        $this->userProvider->expects($this->never())->method('getByCredentials');
    }

    private function makeController(array $guzzleResponses = []): AuthController
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($guzzleResponses));
        $stack->push(Middleware::history($this->history));

        $config = new Config([
            'app_url' => 'https://tv-calendar.test',
            'app_key' => 'test-app-key',
            'oauth' => [
                'google' => [
                    'client_id'     => 'client-id',
                    'client_secret' => 'client-secret',
                    'redirect_uri'  => '/google-oauth',
                ],
            ],
        ]);

        $validator = $this->createMock(RequestValidatorInterface::class);
        $validator->method('validate')->willReturnArgument(0);

        $validatorFactory = $this->createMock(RequestValidatorFactoryInterface::class);
        $validatorFactory->method('make')->willReturn($validator);

        return new AuthController(
            $this->createMock(Twig::class),
            $validatorFactory,
            $this->auth,
            $this->userShowsService,
            $this->userProvider,
            $this->createMock(ResponseFormatter::class),
            $this->createMock(VerificatonEmail::class),
            $config,
            new Client(['handler' => $stack]),
            $this->session
        );
    }

    private function request(array $queryParams): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://tv-calendar.test/google-oauth')
            ->withQueryParams($queryParams);
    }

    private function locationQuery(ResponseInterface $response): array
    {
        $query = parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY);

        parse_str(is_string($query) ? $query : '', $params);

        return $params;
    }
}
