<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\User;
use Doctrine\ORM\EntityManager;

class UserSettingsService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {
    }

    public function setStartOfWeek(User $user, bool $startOfWeekSunday): void
    {
        $user->setStartOfWeekSunday($startOfWeekSunday);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
