<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Show;
use Doctrine\ORM\EntityManager;

class ShowService
{

    public function __construct(private readonly EntityManager $entityManager) {}

    public function getById(int $id): ?Show
    {
        return $this->entityManager->find(Show::class, $id);
    }
}
