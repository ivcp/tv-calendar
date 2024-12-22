<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\EpisodeData;
use App\Entity\Episode;
use App\Entity\Show;
use DateTime;
use Doctrine\ORM\EntityManager;

class EpisodeService
{

    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

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

    public function create(EpisodeData $episodeData, Show $show): Episode
    {
        $episode = new Episode();
        $episode->setTvMazeShowId($episodeData->tvMazeShowId);
        $episode->setTvMazeEpisodeId($episodeData->tvMazeEpisodeId);
        $episode->setName($episodeData->episodeName);
        $episode->setSeason($episodeData->seasonNumber);
        $episode->setNumber($episodeData->episodeNumber);
        $episode->setSummary($episodeData->episodeSummary);
        $episode->setType($episodeData->type);
        $episode->setAirstamp($episodeData->airstamp);
        $episode->setRuntime($episodeData->runtime);
        $episode->setImageMedium($episodeData->imageMedium);
        $episode->setImageOriginal($episodeData->imageOriginal);

        $episode->setShow($show);

        return $episode;
    }
}
