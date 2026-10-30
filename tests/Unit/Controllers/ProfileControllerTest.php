<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Config;
use App\Controllers\ProfileController;
use App\Contracts\AuthInterface;
use App\Contracts\RequestValidatorInterface;
use App\Contracts\UserProviderServiceInterface;
use App\Entity\User;
use App\Notifications\DiscordNotificationSender;
use App\RequestValidators\RequestValidatorFactory;
use App\ResponseFormatter;
use App\Services\NtfyService;
use App\Services\RequestService;
use App\Services\UserSettingsService;
use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\Unit\Support\InMemorySession;

class ProfileControllerTest extends TestCase
{
    private UserSettingsService&MockObject $userSettingsService;
    private NtfyService&MockObject $ntfyService;
    private RequestValidatorFactory&MockObject $validatorFactory;

    protected function setUp(): void
    {
        $this->userSettingsService = $this->createMock(UserSettingsService::class);
        $this->ntfyService = $this->createMock(NtfyService::class);

        $validator = $this->createMock(RequestValidatorInterface::class);
        $validator->method('validate')->willReturnArgument(0);

        $this->validatorFactory = $this->createMock(RequestValidatorFactory::class);
        $this->validatorFactory->method('make')->willReturn($validator);
    }

    public function testUnverifiedUserCannotEnableNtfyNotifications(): void
    {
        $user = $this->user(verified: false);

        $this->userSettingsService->expects($this->never())->method('setupNtfyNotifications');
        $this->ntfyService->expects($this->never())->method('createUser');

        $response = $this->controller()->updateSettings(
            $this->request($user, [
                'notificationsPassword' => 'password123',
                'confirmNotificationsPassword' => 'password123',
            ]),
            new Response()
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            'Please verify your email to use notifications',
            $this->json($response)['msg'] ?? null
        );
    }

    public function testUnverifiedUserCannotSendTestNtfyMessage(): void
    {
        $user = $this->user(verified: false);

        $this->ntfyService->expects($this->never())->method('sendNotification');

        $response = $this->controller()->sendTestNtfyMessage(
            $this->request($user),
            new Response()
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            'Please verify your email to use notifications',
            $this->json($response)['msg'] ?? null
        );
    }

    public function testVerifiedUserCanEnableNtfyNotifications(): void
    {
        $user = $this->user(verified: true);

        $this->userSettingsService
            ->expects($this->once())
            ->method('setupNtfyNotifications')
            ->with($user, 'password123');

        $response = $this->controller()->updateSettings(
            $this->request($user, [
                'notificationsPassword' => 'password123',
                'confirmNotificationsPassword' => 'password123',
            ]),
            new Response()
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('settings saved', $this->json($response)['msg'] ?? null);
    }

    public function testVerifiedUserCanSendTestNtfyMessage(): void
    {
        $user = $this->user(verified: true);
        $user->setNtfyTopic('abc123');

        $this->ntfyService
            ->expects($this->once())
            ->method('sendNotification')
            ->with('abc123', 'Test', 'This is a test message');

        $response = $this->controller()->sendTestNtfyMessage(
            $this->request($user),
            new Response()
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('test notification sent', $this->json($response)['msg'] ?? null);
    }

    private function controller(): ProfileController
    {
        return new ProfileController(
            $this->createMock(Twig::class),
            new ResponseFormatter(new Config(['json_tags' => JSON_UNESCAPED_SLASHES])),
            $this->createMock(UserProviderServiceInterface::class),
            $this->createMock(AuthInterface::class),
            $this->validatorFactory,
            $this->userSettingsService,
            new RequestService(new InMemorySession()),
            $this->ntfyService,
            new Config(['app_url' => 'https://tv-calendar.test']),
            $this->createMock(DiscordNotificationSender::class),
        );
    }

    private function user(bool $verified): User
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setVerifiedAt($verified ? new DateTime() : null);

        return $user;
    }

    private function request(User $user, array $body = []): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('PATCH', '/profile')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withParsedBody($body)
            ->withAttribute('user', $user);
    }

    private function json(Response $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
