<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\AuthInterface;
use App\Contracts\UserProviderServiceInterface;
use App\RequestValidators\EnableNotificationsRequestValidator;
use App\RequestValidators\RequestValidatorFactory;
use App\RequestValidators\StartOfWeekRequestValidator;
use App\ResponseFormatter;
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
        private readonly RequestService $requestService
    ) {
    }
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
                'ntfyTopic' => $user->getNtfyTopic()
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
}
