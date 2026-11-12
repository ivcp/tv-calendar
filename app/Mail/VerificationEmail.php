<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\User;
use App\Services\EmailSendingService;
use DateTime;

class VerificationEmail
{
    public function __construct(
        private readonly VerificationLinkService $linkService,
        private readonly EmailSendingService $emailSendingService

    ) {}

    public function send(User $user): void
    {
        $expirationDate = new DateTime('+ 48 hours');
        $email = $user->getEmail();
        $activationLink = $this->linkService->forUser($user, $expirationDate);

        $this->emailSendingService->queue(
            to: $email,
            subject: 'Confirm your email address',
            htmlTemplate: 'emails/register.html.twig',
            activationLink: $activationLink,
            expirationDate: $expirationDate
        );
    }
}
