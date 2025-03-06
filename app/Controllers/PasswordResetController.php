<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\RequestValidatorFactoryInterface;
use App\Contracts\UserProviderServiceInterface;
use App\Exception\ValidationException;
use App\Mail\ForgotPasswordEmail;
use App\RequestValidators\ForgotPasswordRequestValidator;
use App\RequestValidators\ResetPasswordRequestValidator;
use App\Services\PasswordResetService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class PasswordResetController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly RequestValidatorFactoryInterface $requestValidatorFactory,
        private readonly UserProviderServiceInterface $userProviderService,
        private readonly PasswordResetService $passwordResetService,
        private readonly ForgotPasswordEmail $forgotPasswordEmail
    ) {
    }
    public function forgotPasswordView(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'auth/forgot-password.twig');
    }

    public function resetPasswordView(Request $request, Response $response, array $args): Response
    {
        $token = $args['token'];
        $passwordReset = $this->passwordResetService->findByToken($token);
        if (!$passwordReset) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }
        return $this->twig->render($response, 'auth/reset-password.twig', ['token' => $token]);
    }

    public function handleForgotPassword(Request $request, Response $response): Response
    {
        $data = $this->requestValidatorFactory
        ->make(ForgotPasswordRequestValidator::class)
        ->validate($request->getParsedBody());

        $user = $this->userProviderService->getByCredentials($data);
        if ($user) {
            $this->passwordResetService->deactivateAllPasswordResets($data['email']);
            $passwordReset = $this->passwordResetService->generate($user->getEmail());
            $this->forgotPasswordEmail->send($passwordReset);
        }

        return $this->twig->render($response, 'auth/forgot-password.twig', ['emailSent' => $data['email']]);
    }
    public function resetPassword(Request $request, Response $response, array $args): Response
    {
        $data = $this->requestValidatorFactory->make(ResetPasswordRequestValidator::class)->validate(
            $request->getParsedBody()
        );

        $passwordReset = $this->passwordResetService->findByToken($args['token']);

        if (! $passwordReset) {
            throw new ValidationException(['confirm_password' => ['Invalid token']]);
        }

        $user = $this->userProviderService->getByCredentials(['email' => $passwordReset->getEmail()]);

        if (! $user) {
            throw new ValidationException(['confirm_password' => ['Invalid token']]);
        }

        $this->passwordResetService->updatePassword($user, $data['password']);

        return $this->twig->render($response, 'auth/reset-password.twig', ['passwordReset' => true]);
    }

}
