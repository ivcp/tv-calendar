<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Contracts\AuthInterface;
use App\Contracts\UserProviderServiceInterface;
use App\DataObjects\NotificationMessage;
use App\Enum\NotificationTime;
use App\Notifications\DiscordNotification;
use App\RequestValidators\EnableDiscordNotificationsRequestValidator;
use App\RequestValidators\EnableNtfyNotificationsRequestValidator;
use App\RequestValidators\RequestValidatorFactory;
use App\RequestValidators\SetNotificationTimeRequestValidator;
use App\RequestValidators\StartOfWeekRequestValidator;
use App\ResponseFormatter;
use App\Services\NtfyService;
use App\Services\RequestService;
use App\Services\UrlProtectionService;
use App\Services\UserSettingsService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class ProfileController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly ResponseFormatter $responseFormatter,
        private readonly UserProviderServiceInterface $userProviderService,
        private readonly AuthInterface $auth,
        private readonly RequestValidatorFactory $requestValidatorFactory,
        private readonly UserSettingsService $userSettingsService,
        private readonly RequestService $requestService,
        private readonly NtfyService $ntfyService,
        private readonly Config $config,
        private readonly DiscordNotification $discordNotification,
    ) {}
    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        return $this->twig->render(
            $response,
            'profile/index.twig',
            [
                'email' => $user->getEmail(),
                'verified' => $user->getVerifiedAt(),
                'passwordSet' => $user->getPassword() !== null,
                'ntfyTopic' => $user->getNtfyTopic(),
                'discordWebhookUrl' => $user->getDiscordWebhookUrl(),
                'startOfWeekSunday' => $user->getStartOfWeekSunday(),
                'notificationTime' => $user->getNotificationTime()->value
            ]
        );
    }
    public function delete(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $this->userProviderService->deleteUser($user);
        $this->auth->logout();
        return $this->responseFormatter->asJSONMessage($response, 200, 'profile deleted');
    }

    public function updateSettings(Request $request, Response $response): Response
    {
        if (!$this->requestService->isXhr($request)) {
            return $this->responseFormatter->asJSONErrors($response->withStatus(400), 'bad request');
        }
        $body =  $request->getParsedBody();

        if (array_key_exists('startOfWeekSunday', $body)) {
            return $this->setStartOfWeek($request, $response);
        };

        if (array_key_exists('notificationsPassword', $body)) {
            return $this->enableNtfyNotifications($request, $response);
        };

        if (array_key_exists('discordWebhookUrl', $body)) {
            return $this->enableDiscordNotifications($request, $response);
        };

        if (array_key_exists('disableNtfyNotifications', $body)) {
            return $this->disableNtfyNotifications($request, $response);
        };
        if (array_key_exists('disableDiscordNotifications', $body)) {
            return $this->disableDiscordNotifications($request, $response);
        };

        if (array_key_exists('notificationTime', $body)) {
            return $this->setNotificationTime($request, $response);
        };

        return $this->responseFormatter->asJSONErrors($response->withStatus(400), 'bad request');
    }

    private function setStartOfWeek(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory->make(StartOfWeekRequestValidator::class)->validate(
            $request->getParsedBody()
        );
        $user = $request->getAttribute('user');
        $this->userSettingsService->setStartOfWeek($user, $data['startOfWeekSunday']);

        return $this->responseFormatter->asJSONMessage($response, 200, 'settings saved');
    }

    private function enableNtfyNotifications(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory->make(EnableNtfyNotificationsRequestValidator::class)->validate(
            $request->getParsedBody()
        );

        $user = $request->getAttribute('user');
        $this->userSettingsService->setupNtfyNotifications($user, $data['notificationsPassword']);

        return $this->responseFormatter->asJSONMessage($response, 200, 'settings saved');
    }

    private function enableDiscordNotifications(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory->make(EnableDiscordNotificationsRequestValidator::class)->validate(
            $request->getParsedBody()
        );

        $user = $request->getAttribute('user');
        $this->userSettingsService->setupDiscordNotifications($user, $data['discordWebhookUrl']);

        return $this->responseFormatter->asJSONMessage($response, 200, 'settings saved');
    }

    private function disableNtfyNotifications(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $this->userSettingsService->disableNtfyNotifications($user);
        return $this->responseFormatter->asJSONMessage($response, 200, 'settings saved');
    }
    private function disableDiscordNotifications(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $this->userSettingsService->disableDiscordNotifications($user);
        return $this->responseFormatter->asJSONMessage($response, 200, 'settings saved');
    }

    private function setNotificationTime(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory->make(SetNotificationTimeRequestValidator::class)->validate(
            $request->getParsedBody()
        );

        $user = $request->getAttribute('user');
        $this->userSettingsService->setNotificationTime(
            $user,
            NotificationTime::from($data['notificationTime'])
        );
        return $this->responseFormatter->asJSONMessage($response, 200, 'settings saved');
    }

    public function sendTestNtfyMessage(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        $topic = $user->getNtfyTopic();

        $this->ntfyService->sendNotification($topic, 'Test', 'This is a test message');

        return $this->responseFormatter->asJSONMessage($response, 200, 'test notification sent');
    }

    public function sendTestDiscordMessage(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        $encodedUrl = $user->getDiscordWebhookUrl();

        $urlProtectionService = new UrlProtectionService($this->config->get('url_secret_key'));

        $url = $urlProtectionService->decrypt($encodedUrl);

        $this->discordNotification->send(
            new NotificationMessage(
                $url,
                'It works!',
                'This is a test message from',
                $this->config->get('app_url')
            )
        );

        return $this->responseFormatter->asJSONMessage($response, 200, 'test notification sent');
    }
}
