<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\EpisodeData;
use App\Entity\Episode;
use App\Entity\Show;
use App\Services\Traits\SetParameterAndType;
use DateTime;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManager;
use Iterator;
use SplFixedArray;

class EpisodeService
{

    use SetParameterAndType;

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

    /**
     * Bulk insert episodes
     *
     * @param EpisodeData[] $episodes 
     * @return int number of episodes inserted
     **/
    public function insertEpisodes(array $episodes): int
    {
        if (!$episodes) {
            return 0;
        }

        $conn = $this->entityManager->getConnection();


        $episodeCount = count($episodes);
        $values = array_fill(
            0,
            $episodeCount,
            "(?, ?, ?, ?::timestamptz, ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp, ?)"
        );


        $params = new SplFixedArray($episodeCount * 11);
        $types = new SplFixedArray($episodeCount * 11);
        $paramsIterator = $params->getIterator();
        foreach ($episodes as $episode) {
            $this->setParameterAndType($params, $types, $paramsIterator, $episode->tvMazeEpisodeId, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types,  $paramsIterator, $episode->seasonNumber, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types, $paramsIterator,  $episode->episodeNumber, ParameterType::INTEGER);

            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->airstamp ? $episode->airstamp->format(DATE_ATOM) : null,
                ParameterType::STRING
            );
            $this->setParameterAndType($params,  $types, $paramsIterator,  $episode->type, ParameterType::STRING);
            $this->setParameterAndType($params,  $types, $paramsIterator, $episode->episodeSummary, ParameterType::STRING);
            $this->setParameterAndType($params,  $types, $paramsIterator, $episode->episodeName, ParameterType::STRING);
            $this->setParameterAndType($params,  $types, $paramsIterator, $episode->runtime, ParameterType::INTEGER);
            $this->setParameterAndType($params,  $types, $paramsIterator,  $episode->imageMedium, ParameterType::STRING);
            $this->setParameterAndType($params,  $types, $paramsIterator, $episode->imageOriginal, ParameterType::STRING);
            $this->setParameterAndType($params,  $types, $paramsIterator,  $episode->tvMazeShowId, ParameterType::INTEGER);
        }


        $rows = $conn->executeStatement('INSERT INTO episodes 
        (tv_maze_episode_id, season, number, airstamp, type, summary, name, 
        runtime, image_medium, image_original, created_at, updated_at, tv_maze_show_id)
        VALUES ' . implode(',', $values), $params->toArray(), $types->toArray());


        return (int) $rows;
    }
}
