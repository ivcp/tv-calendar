<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\BodyRendererInterface;

class WelcomeEmail
{
    public function __construct(
        private readonly Config $config,
        private readonly MailerInterface $mailer,
        private readonly BodyRendererInterface $bodyRenderer,
    ) {
    }

    public function send(User $user): void
    {
        $email = $user->getEmail();

        $message = (new TemplatedEmail())
        ->from($this->config->get('mailer.from'))
        ->to($email)
        ->subject('Welcome to ' . $this->config->get('app_name'))
        ->htmlTemplate('emails/welcome.html.twig')
        ->context([
            'website' => $this->config->get('app_url')
        ]);

        $this->bodyRenderer->render($message);

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            error_log($e->getMessage());
        }
    }
}
