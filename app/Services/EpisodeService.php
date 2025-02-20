<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\DataObjects\EpisodeData;
use App\Entity\User;
use App\Services\Traits\ParamsTypesCases;
use DateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManager;
use Exception;
use SplFixedArray;

class EpisodeService
{
    use ParamsTypesCases;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
    }

    public function getEpisodesForMonth(DateTime $month, string $timeZone, ?User $user = null): array
    {

        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT e.id, s.name as "showName", e.name as "episodeName",
                e.season, e.number, e.summary, e.type, e.airstamp,
                e.image_medium as image, s.id as "showId", s.network_name as "networkName", 
                s.web_channel_name as "webChannelName"
                FROM episodes e
                INNER JOIN shows s ON s.id = e.show_id';
        $sql .= $user ? ' INNER JOIN users_shows us ON us.show_id = s.id' : '';
        $sql .= ' WHERE (e.airstamp AT TIME ZONE :tz BETWEEN :first AND :last)';
        $sql .= $user ? ' AND us.user_id = :userId ' : ' AND s.weight >= :weight';
        $sql .= ' ORDER BY e.airstamp ASC, e.id ASC';

        $stmt = $conn->prepare($sql);

        $stmt->bindValue('tz', $timeZone);
        $stmt->bindValue('first', $month->format('Y-m-1'));
        $stmt->bindValue('last', $month->format("Y-m-t 23:59"));

        if (!$user) {
            $stmt->bindValue('weight', $this->config->get('popular_weight'), ParameterType::INTEGER);
        } else {
            $stmt->bindValue('userId', $user->getId(), ParameterType::INTEGER);
        }

        return $stmt->executeQuery()->fetchAllAssociative();
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
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->tvMazeEpisodeId,
                ParameterType::INTEGER
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->seasonNumber,
                ParameterType::INTEGER
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->episodeNumber,
                ParameterType::INTEGER
            );

            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->airstamp ? $episode->airstamp->format(DATE_ATOM) : null,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->type,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->episodeSummary,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->episodeName,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->runtime,
                ParameterType::INTEGER
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->imageMedium,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->imageOriginal,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $episode->tvMazeShowId,
                ParameterType::INTEGER
            );
        }

        $paramsArray = $params->toArray();
        $typesArray = $types->toArray();

        $rowsInserted = 0;
        try {
            if (count($paramsArray) < 65535) {
                $rowsInserted = $conn->executeStatement('INSERT INTO episodes 
                (tv_maze_episode_id, season, number, airstamp, type, summary, name, 
                runtime, image_medium, image_original, created_at, updated_at, tv_maze_show_id)
                VALUES ' . implode(',', $values), $paramsArray, $typesArray);
            } else {
                $chunkedValues = array_chunk($values, 5000);
                $chunkedParams = array_chunk($paramsArray, 55000);
                $chunkedTypes = array_chunk($typesArray, 55000);
                foreach ($chunkedValues as $i => $chunk) {
                    $rowsInserted += $conn->executeStatement('INSERT INTO episodes 
                    (tv_maze_episode_id, season, number, airstamp, type, summary, name, 
                    runtime, image_medium, image_original, created_at, updated_at, tv_maze_show_id)
                    VALUES ' . implode(',', $chunk), $chunkedParams[$i], $chunkedTypes[$i]);
                }
            }
        } catch (Exception $e) {
            throw $e;
        }

        return (int) $rowsInserted;
    }


    /**
     * Connect inserted episodes to shows
     *
     * @return int number of episodes updated
     **/
    public function connectEpisodesWithShows(): int
    {
        $conn = $this->entityManager->getConnection();

        $rows = $conn->executeStatement("UPDATE episodes 
        SET show_id = shows.id
        FROM shows
        WHERE episodes.show_id IS NULL
        AND episodes.tv_maze_show_id = shows.tv_maze_id;");

        return (int) $rows;
    }

    /**
     * Update episodes
     *
     * @param EpisodeData[] $episodes
     * @return int number of episodes updated
     **/
    public function updateEpisodes(array $episodes, int $showId): int
    {
        if (!$episodes) {
            return 0;
        }

        $conn = $this->entityManager->getConnection();

        $ids = array_keys($episodes);
        $updatebles = [
            'episodeName' => ParameterType::STRING,
            'seasonNumber' => ParameterType::INTEGER,
            'episodeNumber' => ParameterType::INTEGER,
            'airstamp' => ParameterType::STRING,
            'type' => ParameterType::STRING,
            'episodeSummary' => ParameterType::STRING,
            'runtime' => ParameterType::INTEGER,
            'imageMedium' => ParameterType::STRING,
            'imageOriginal' => ParameterType::STRING,
        ];

        $cases = [];
        $params = new SplFixedArray(count($ids) * count($updatebles) + 2);
        $types = new SplFixedArray(count($ids) * count($updatebles) + 2);
        $it = $params->getIterator();

        foreach ($updatebles as $updatable => $type) {
            $this->setCaseAndParams($updatable, $type, $params, $types, $it, $episodes, $cases);
        }

        [
            $nameCase,
            $seasonNumberCase,
            $episodeNumberCase,
            $airstampCase,
            $typeCase,
            $episodeSummaryCase,
            $runtimeCase,
            $imageMediumCase,
            $imageOriginalCase,

        ] = array_map(function ($updatable) use ($cases) {
            return implode(' ', $cases[$updatable]);
        }, array_keys($updatebles));


        $this->setParameterAndType($params, $types, $it, $showId, ParameterType::INTEGER);
        $this->setParameterAndType($params, $types, $it, $ids, ArrayParameterType::INTEGER);


        $rows = 0;
        try {
            if ($params->count() < 65535) {
                $rows = $conn->executeStatement(
                    "UPDATE episodes 
                    SET 
                        name = CASE $nameCase END,
                        season = CASE $seasonNumberCase END,
                        number = CASE $episodeNumberCase END,
                        airstamp = CASE $airstampCase END,
                        type = CASE $typeCase END,
                        summary = CASE $episodeSummaryCase END,
                        runtime = CASE $runtimeCase END,
                        image_medium = CASE $imageMediumCase END,
                        image_original = CASE $imageOriginalCase END,
                        updated_at = current_timestamp                        
                 WHERE show_id = ? AND id IN (?);",
                    $params->toArray(),
                    $types->toArray(),
                );
            }
        } catch (Exception $e) {
            throw $e;
        }

        return (int) $rows;
    }
    /**
     * Remove episodes
     * @return int number of episodes removed
     **/
    public function removeEpisodes(array $ids): int
    {
        $q = $this->entityManager->createQuery('DELETE from App\Entity\Episode e WHERE e.id IN (:ids)');
        $q->setParameter('ids', $ids, ArrayParameterType::INTEGER);
        return (int) $q->execute();
    }
}
