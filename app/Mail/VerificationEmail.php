<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use App\Entity\User;
use App\Services\EmailSendingService;
use DateTime;

class VerificationEmail
{
    public function __construct(
        private readonly Config $config,
        private readonly VerificationLinkService $linkService,
        private readonly EmailSendingService $emailSendingService

    ) {}

    public function send(User $user): void
    {
        $expirationDate = new DateTime('+ 48 hours');
        $email = $user->getEmail();
        $activationLink = $this->linkService->forUser($user, $expirationDate);
        $appName = $this->config->get('app_name');

        $this->emailSendingService->queue(
            to: $email,
            subject: "Welcome to $appName",
            htmlTemplate: 'emails/register.html.twig',
            activationLink: $activationLink,
            expirationDate: $expirationDate
        );
    }
}
