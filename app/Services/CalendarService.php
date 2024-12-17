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

        //TODO: episode service
        $episodes = $this->entityManager->getRepository(Episode::class)
            ->createQueryBuilder('e')
            ->select('e.id, s.name as showName, e.name as episodeName, 
            e.season, e.number, e.summary, e.type, e.airstamp')
            ->where('e.airstamp BETWEEN :first AND :last')
            ->andWhere('s.weight = 100')
            ->innerJoin('e.show', 's')
            ->orderBy('e.airstamp')
            ->setParameter('first', $selectedMonth->format('Y-m-1'))
            ->setParameter('last', $selectedMonth->format("Y-m-$daysInMonth"))
            ->getQuery()
            ->getResult();


        $scheduleData = array_map(function ($episode) {
            return new ScheduleData(
                id: $episode['id'],
                showName: $episode['showName'],
                episodeName: $episode['episodeName'],
                seasonNumber: $episode['season'],
                episodeNumber: $episode['number'],
                episodeSummary: $episode['summary'],
                type: $episode['type'],
                airstamp: $episode['airstamp']->format(DATE_ATOM)
            );
        }, $episodes);



        $popularSchedule = $this->sortByDates((int) $daysInMonth, $scheduleData);

        return ['popular' => $popularSchedule, 'my_shows' => []];
    }




    private function sortByDates(int $daysInMonth, array $episodes): array
    {
        $sorted = [];

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $sorted[$i] = array_values(array_filter(
                $episodes,
                fn($show) => (new DateTime($show->airstamp))->format('j') == $i
            ));
        }
        return $sorted;
    }
}
