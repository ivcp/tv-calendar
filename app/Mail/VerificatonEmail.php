<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use App\Entity\User;
use App\Services\EmailSendingService;
use DateTime;
use Slim\Interfaces\RouteParserInterface;

class VerificatonEmail
{
    public function __construct(
        private readonly Config $config,
        private readonly RouteParserInterface $routeParser,
        private readonly EmailSendingService $emailSendingService

    ) {}

    public function send(User $user): void
    {
        $expirationDate = new DateTime('+ 48 hours');
        $email = $user->getEmail();
        $activationLink = $this->generateSignedUrl(
            $user->getId(),
            $email,
            $expirationDate
        );
        $appName = $this->config->get('app_name');

        $this->emailSendingService->queue(
            to: $email,
            subject: "Welcome to $appName",
            htmlTemplate: 'emails/register.html.twig',
            activationLink: $activationLink,
            expirationDate: $expirationDate
        );
    }

    private function generateSignedUrl(int $userId, string $email, DateTime $expirationDate): string
    {
        $expiration = $expirationDate->getTimestamp();
        $routeParams = ['id' => $userId, 'hash' => sha1($email)];
        $queryParams = ['expiration' => $expiration];
        $baseUrl = trim($this->config->get('app_url'), '/');
        $url = $baseUrl . $this->routeParser->urlFor('verify', $routeParams, $queryParams);
        $signature = hash_hmac('sha256', $url, $this->config->get('app_key'));

        error_log(sprintf('URL GENERATED: %s', $url));
        error_log(sprintf('SIGNATURE GENERATED: %s', $signature));

        return $baseUrl . $this->routeParser->urlFor(
            'verify',
            $routeParams,
            $queryParams + ['signature' => $signature]
        );
    }
}
