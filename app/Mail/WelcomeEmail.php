<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use App\Entity\User;
use App\Services\EmailSendingService;

class WelcomeEmail
{
    public function __construct(
        private readonly Config $config,
        private readonly EmailSendingService $emailSendingService
    ) {}

    public function send(User $user): void
    {
        $email = $user->getEmail();
        $appName = $this->config->get('app_name');

        $this->emailSendingService->queue(
            to: $email,
            subject: "Welcome to $appName",
            htmlTemplate: 'emails/welcome.html.twig'
        );
    }
}
