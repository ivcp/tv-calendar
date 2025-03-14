<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use App\Entity\User;
use DateTime;
use Slim\Interfaces\RouteParserInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\BodyRendererInterface;

class VerificatonEmail
{
    public function __construct(
        private readonly Config $config,
        private readonly MailerInterface $mailer,
        private readonly BodyRendererInterface $bodyRenderer,
        private readonly RouteParserInterface $routeParser
    ) {
    }

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

        $message = (new TemplatedEmail())
        ->from($this->config->get('mailer.from'))
        ->to($email)
        ->subject('Welcome to ' . $appName)
        ->htmlTemplate('emails/register.html.twig')
        ->context([
            'activationLink' => $activationLink,
            'expirationDate' => $expirationDate,
            'appName' => $appName
        ]);
        $this->bodyRenderer->render($message);

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            error_log($e->getMessage());
        }
    }

    private function generateSignedUrl(int $userId, string $email, DateTime $expirationDate): string
    {
        $expiration = $expirationDate->getTimestamp();
        $routeParams = ['id' => $userId, 'hash' => sha1($email)];
        $queryParams = ['expiration' => $expiration];
        $baseUrl = trim($this->config->get('app_url'), '/');
        $url = $baseUrl . $this->routeParser->urlFor('verify', $routeParams, $queryParams);
        $signature = hash_hmac('sha256', $url, $this->config->get('app_key'));

        return $baseUrl . $this->routeParser->urlFor(
            'verify',
            $routeParams,
            $queryParams + ['signature' => $signature]
        );
    }
}
