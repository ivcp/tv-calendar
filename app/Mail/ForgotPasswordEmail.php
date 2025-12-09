<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use App\Entity\PasswordReset;
use App\Services\EmailSendingService;
use DateTime;
use Slim\Interfaces\RouteParserInterface;


class ForgotPasswordEmail
{
    public function __construct(
        private readonly Config $config,
        private readonly RouteParserInterface $routeParser,
        private readonly EmailSendingService $emailSendingService

    ) {}

    public function send(PasswordReset $passwordReset): void
    {
        $email = $passwordReset->getEmail();
        $resetLink = $this->generateResetLink(
            $passwordReset->getToken(),
            $passwordReset->getExpiration()
        );
        $appName = $this->config->get('app_name');

        $this->emailSendingService->queue(
            to: $email,
            subject: "$appName - password reset",
            htmlTemplate: 'emails/password-reset.html.twig',
            resetLink: $resetLink
        );
    }

    private function generateResetLink(string $token, DateTime $expirationDate): string
    {
        $expiration = $expirationDate->getTimestamp();
        $routeParams = ['token' => $token];
        $queryParams = ['expiration' => $expiration];
        $baseUrl = trim($this->config->get('app_url'), '/');
        $url = $baseUrl . $this->routeParser->urlFor('password-reset', $routeParams, $queryParams);
        $signature = hash_hmac('sha256', $url, $this->config->get('app_key'));

        return $baseUrl . $this->routeParser->urlFor(
            'password-reset',
            $routeParams,
            $queryParams + ['signature' => $signature]
        );
    }
}
