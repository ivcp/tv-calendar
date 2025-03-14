<?php

declare(strict_types=1);

namespace App\Services;

use DateTime;
use Doctrine\ORM\EntityManager;

class AccountCleanupService
{
    public function __construct(
        private readonly EntityManager $entityManager
    ) {
    }

    public function run(): int
    {
        $query = $this->entityManager->createQuery(
            'delete from App\Entity\User u where u.createdAt < :now and u.verifiedAt is NULL'
        )->setParameter('now', new DateTime('5 days ago'));
        return (int) $query->execute();
    }
}
