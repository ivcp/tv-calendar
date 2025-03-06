<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use App\Entity\PasswordReset;
use App\Entity\User;
use DateTime;
use Slim\Interfaces\RouteParserInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\BodyRendererInterface;

class ForgotPasswordEmail
{
    public function __construct(
        private readonly Config $config,
        private readonly MailerInterface $mailer,
        private readonly BodyRendererInterface $bodyRenderer,
        private readonly RouteParserInterface $routeParser,
    ) {
    }

    public function send(PasswordReset $passwordReset): void
    {
        $email = $passwordReset->getEmail();
        $resetLink = $this->generateResetLink(
            $passwordReset->getToken(),
            $passwordReset->getExpiration()
        );

        $message = (new TemplatedEmail())
        ->from($this->config->get('mailer.from'))
        ->to($email)
        ->subject('TV Calendar - password reset')
        ->htmlTemplate('emails/password-reset.html.twig')
        ->context([
            'resetLink' => $resetLink
        ]);
        $this->bodyRenderer->render($message);

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            error_log($e->getMessage());
        }
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
