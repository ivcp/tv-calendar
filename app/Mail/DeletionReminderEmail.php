<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use App\Entity\User;
use App\Services\EmailSendingService;
use DateTime;

class DeletionReminderEmail
{
    public function __construct(
        private readonly Config $config,
        private readonly VerificationLinkService $linkService,
        private readonly EmailSendingService $emailSendingService
    ) {}

    public function send(User $user, DateTime $deletionDate): void
    {
        $appName = $this->config->get('app_name');

        // The cleanup runs once a day and can lag behind the nominal deletion
        // date, so the link stays valid for a day after it to avoid dead-ending
        // a user whose account still exists.
        $linkExpiration = (clone $deletionDate)->modify('+1 day');

        $this->emailSendingService->queue(
            to: $user->getEmail(),
            subject: "Your $appName account is scheduled for deletion",
            htmlTemplate: 'emails/deletion-reminder.html.twig',
            activationLink: $this->linkService->forUser($user, $linkExpiration),
            expirationDate: $deletionDate,
            showsCount: $user->getShows()->count()
        );
    }
}
