<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\AuthInterface;
use App\Contracts\RequestValidatorFactoryInterface;
use App\Contracts\UserProviderServiceInterface;
use App\DataObjects\RegisterUserData;
use App\Exception\ValidationException;
use App\Mail\VerificatonEmail;
use App\RequestValidators\LoginUserRequestValidator;
use App\RequestValidators\RegisterUserRequestValidator;
use App\ResponseFormatter;
use App\Services\UserShowsService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class AuthController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly RequestValidatorFactoryInterface $requestValidatorFactory,
        private readonly AuthInterface $auth,
        private readonly UserShowsService $userShowsService,
        private readonly UserProviderServiceInterface $userProviderService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly VerificatonEmail $verificatonEmail
    ) {
    }

    public function loginView(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'auth/login.twig');
    }

    public function registerView(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'auth/register.twig');
    }



    public function register(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory
            ->make(RegisterUserRequestValidator::class)
            ->validate($request->getParsedBody());


        $user = $this->auth->register(
            new RegisterUserData(
                email: $data['email'],
                password: $data['password']
            )
        );

        if (isset($data['shows'])) {
            $this->importLocalShows($data['shows'], $user);
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory
            ->make(LoginUserRequestValidator::class)
            ->validate($request->getParsedBody());


        $user = $this->auth->attemptLogin($data);
        if (! $user) {
            throw new ValidationException(['password' => ['Invalid email or password']]);
        }

        if (isset($data['shows'])) {
            $this->importLocalShows($data['shows'], $user);
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->auth->logout();
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function verify(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');

        if (
            ! hash_equals((string) $user->getId(), $args['id']) ||
            ! hash_equals(sha1($user->getEmail()), $args['hash'])) {
            return $this->twig->render($response, 'auth/verify.twig', ['verified' => false]);
        }

        if (! $user->getVerifiedAt()) {
            $this->userProviderService->verifyUser($user);
        }

        return $this->twig->render($response, 'auth/verify.twig', ['verified' => true]);
    }

    public function resendEmail(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if (! $user) {
            return $this->responseFormatter->asJSONMessage($response, 403, 'unauthorized');
        }
        $this->verificatonEmail->send($user);
        return $this->responseFormatter->asJSONMessage($response, 200, 'new verification email sent');
    }

    private function importLocalShows(array $showIds, $user)
    {
        $localList = array_filter($showIds, "is_numeric");
        if (count($localList) > 10) {
            $localList = array_slice($localList, 0, 10);
        }
        $this->userShowsService->addMultipleShows($localList, $user);
    }
}
