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

        $values = [];

        for ($i = 1; $i <= count($episodes); $i++) {
            $values[] = "(:tvMazeEpisodeId$i, :seasonNumber$i, :episodeNumber$i, :airstamp$i::timestamptz,
            :type$i, :episodeSummary$i, :episodeName$i, :runtime$i, :imageMedium$i, :imageOriginal$i, 
            current_timestamp, current_timestamp, :tvMazeShowId$i)";
        }

        $params = [];
        $types = [];
        foreach ($episodes as $i => $episode) {
            $this->setParameterAndType($params, $types, 'tvMazeEpisodeId', $i, $episode->tvMazeEpisodeId, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types, 'seasonNumber', $i, $episode->seasonNumber, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types, 'episodeNumber', $i, $episode->episodeNumber, ParameterType::INTEGER);
            $this->setParameterAndType(
                $params,
                $types,
                'airstamp',
                $i,
                $episode->airstamp ? $episode->airstamp->format(DATE_ATOM) : null,
                ParameterType::STRING
            );
            $this->setParameterAndType($params, $types, 'type', $i, $episode->type, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'episodeSummary', $i, $episode->episodeSummary, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'episodeName', $i, $episode->episodeName, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'runtime', $i, $episode->runtime, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types, 'imageMedium', $i, $episode->imageMedium, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'imageOriginal', $i, $episode->imageOriginal, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'tvMazeShowId', $i, $episode->tvMazeShowId, ParameterType::INTEGER);
        }


        $rows = $conn->executeStatement('INSERT INTO episodes 
        (tv_maze_episode_id, season, number, airstamp, type, summary, name, 
        runtime, image_medium, image_original, created_at, updated_at, tv_maze_show_id)
        VALUES ' . implode(',', $values), $params, $types);


        return (int) $rows;
    }
}
