<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\AuthInterface;
use App\Contracts\RequestValidatorFactoryInterface;
use App\DataObjects\RegisterUserData;
use App\Exception\ValidationException;
use App\RequestValidators\LoginUserRequestValidator;
use App\RequestValidators\RegisterUserRequestValidator;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class AuthController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly RequestValidatorFactoryInterface $requestValidatorFactory,
        private readonly AuthInterface $auth
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


        $this->auth->register(
            new RegisterUserData(
                email: $data['email'],
                password: $data['password']
            )
        );

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory
            ->make(LoginUserRequestValidator::class)
            ->validate($request->getParsedBody());


        if (! $this->auth->attemptLogin($data)) {
            throw new ValidationException(['password' => ['Invalid email or password']]);
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }
    public function logout(Request $request, Response $response): Response
    {
        $this->auth->logout();
        return $response->withHeader('Location', '/')->withStatus(302);
    }
}
