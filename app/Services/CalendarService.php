<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\ScheduleData;
use App\Entity\Episode;
use App\Entity\Show;
use DateTime;
use Doctrine\ORM\EntityManager;

class CalendarService
{

    public function __construct(private readonly EntityManager $entityManager) {}

    public function getSchedule(string $month): array
    {
        $selectedMonth = new DateTime($month);
        $daysInMonth = $selectedMonth->format('t');


        $episodes = $this->entityManager->getRepository(Episode::class)
            ->createQueryBuilder('e')
            ->select('e.name as episode_name, e.id, e.airstamp, s.name as show_name')
            ->where('e.airstamp BETWEEN :first AND :last')
            ->andWhere('s.weight = 100')
            ->innerJoin('e.show', 's')
            ->orderBy('e.airstamp')
            ->setParameter('first', $selectedMonth->format('Y-m-1'))
            ->setParameter('last', $selectedMonth->format("Y-m-$daysInMonth"))
            ->getQuery()
            ->getResult();

        var_dump($episodes);
        exit;



        return ['popular' => [], 'my_shows' => []];
    }
}
