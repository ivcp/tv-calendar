<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\AuthInterface;
use App\Contracts\UserProviderServiceInterface;
use App\Enum\NotificationTime;
use App\RequestValidators\EnableNotificationsRequestValidator;
use App\RequestValidators\RequestValidatorFactory;
use App\RequestValidators\SetNotificationTimeRequestValidator;
use App\RequestValidators\StartOfWeekRequestValidator;
use App\ResponseFormatter;
use App\Services\NtfyService;
use App\Services\RequestService;
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
        private readonly NtfyService $ntfyService
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
            return $this->enableNotifications($request, $response);
        };

        if (array_key_exists('disableNotifications', $body)) {
            return $this->disableNotifications($request, $response);
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

    private function enableNotifications(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory->make(EnableNotificationsRequestValidator::class)->validate(
            $request->getParsedBody()
        );

        $user = $request->getAttribute('user');
        $this->userSettingsService->setupNotifications($user, $data['notificationsPassword']);

        return $this->responseFormatter->asJSONMessage($response, 200, 'settings saved');
    }

    private function disableNotifications(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $this->userSettingsService->disableNotifications($user);
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
        //
        return $this->responseFormatter->asJSONMessage($response, 200, 'test notification sent');
    }
}
