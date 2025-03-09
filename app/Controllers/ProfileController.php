<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\AuthInterface;
use App\Contracts\UserProviderServiceInterface;
use App\ResponseFormatter;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class ProfileController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly ResponseFormatter $responseFormatter,
        private readonly UserProviderServiceInterface $userProviderService,
        private readonly AuthInterface $auth
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
                'passwordSet' => $user->getPassword() !== null
            ]
        );
    }
    public function delete(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $this->auth->logout($user);
        $this->userProviderService->deleteUser($user);
        return $this->responseFormatter->asJSONMessage($response, 200, 'profile deleted');
    }

}
