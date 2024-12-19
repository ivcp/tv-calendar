<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Episode;
use DateTime;
use Doctrine\ORM\EntityManager;

class EpisodeService
{

    public function __construct(private readonly EntityManager $entityManager) {}

    public function getEpisodesForMonth(DateTime $month): array
    {
        return $this->entityManager->getRepository(Episode::class)
            ->createQueryBuilder('e')
            ->select('e.id, s.name as showName, e.name as episodeName, 
             e.season, e.number, e.summary, e.type, e.airstamp')
            ->where('e.airstamp BETWEEN :first AND :last')
            ->andWhere('s.weight = 100')
            ->innerJoin('e.show', 's')
            ->orderBy('e.airstamp')
            ->setParameter('first', $month->format('Y-m-1'))
            ->setParameter('last', $month->format("Y-m-t"))
            ->getQuery()
            ->getResult();
    }
}
