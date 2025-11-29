<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\User;
use Doctrine\ORM\EntityManager;

class UserSettingsService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly NtfyService $ntfyService
    ) {
    }

    public function setStartOfWeek(User $user, bool $startOfWeekSunday): void
    {
        $user->setStartOfWeekSunday($startOfWeekSunday);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function setupNotifications(User $user, string $notificationsPassword): void
    {
        $topic = $this->generateAndCheckTopic();
        $user->setNtfyTopic($topic);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->ntfyService->createUser($user->getEmail(), $notificationsPassword);

    }

    private function generateAndCheckTopic(): string
    {
        $topic = $this->ntfyService->generateTopic();
        if ($this->entityManager->getRepository(User::class)->findOneBy(['ntfyTopic' => $topic])) {
            $topic = $this->generateAndCheckTopic();
        }
        return $topic;
    }
}
