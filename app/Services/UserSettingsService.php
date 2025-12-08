<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\User;
use App\Enum\NotificationTime;
use Doctrine\ORM\EntityManager;
use RuntimeException;

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

    public function setNotificationTime(User $user, NotificationTime $notificationTime): void
    {
        $user->setNotificationTime($notificationTime);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function setupNotifications(User $user, string $notificationsPassword): void
    {
        $topic = $this->generateAndCheckTopic();
        $user->setNtfyTopic($topic);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        try {
            $this->ntfyService->createUser($user->getEmail(), $notificationsPassword, $topic);
        } catch (RuntimeException $e) {
            $user->setNtfyTopic(null);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            throw $e;
        }
    }

    public function disableNotifications(User $user): void
    {
        $topic = $user->getNtfyTopic();
        $user->setNtfyTopic(null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        try {
            $this->ntfyService->deleteUser($user->getEmail());
        } catch (RuntimeException $e) {
            $user->setNtfyTopic($topic);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            throw $e;
        }
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
