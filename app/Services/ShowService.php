<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\ShowData;
use App\Entity\Show;
use App\Services\Traits\SetParameterAndType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManager;
use SplFixedArray;

class ShowService
{
    use SetParameterAndType;

    public function __construct(private readonly EntityManager $entityManager) {}

    public function getById(int $id): ?Show
    {
        return $this->entityManager->find(Show::class, $id);
    }

    public function getShowsByTvMazeId(array $ids): array
    {
        return $this->entityManager->getRepository(Show::class)->findBy(['tvMazeId' => $ids]);
    }

    public function create(ShowData $showData): Show
    {
        $show = new Show();
        $show->setTvMazeId($showData->tvMazeId)
            ->setName($showData->name)
            ->setStatus($showData->status)
            ->setWeight($showData->weight)
            ->setImdbId($showData->imdbId)
            ->setGenres($showData->genres)
            ->setPremiered($showData->premiered)
            ->setEnded($showData->ended)
            ->setOfficialSite($showData->officialSite)
            ->setNetworkName($showData->networkName)
            ->setNetworkCountry($showData->networkCountry)
            ->setWebChannelName($showData->webChannelName)
            ->setWebChannelCountry($showData->webChannelCountry)
            ->setSummary($showData->summary)
            ->setRuntime($showData->runtime)
            ->setImageMedium($showData->imageMedium)
            ->setImageOriginal($showData->imageOriginal);

        return $show;
    }


    /**
     * Bulk insert shows
     *
     * @param ShowData[] $shows 
     * @return int number of shows inserted
     **/
    public function insertShows(array $shows): int
    {
        if (!$shows) {
            return 0;
        }

        $conn = $this->entityManager->getConnection();


        $showCount = count($shows);
        $values = array_fill(
            0,
            $showCount,
            "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp)"
        );

        $params = new SplFixedArray($showCount * 17);
        $types = new SplFixedArray($showCount * 17);
        $paramsIterator = $params->getIterator();
        foreach ($shows as $show) {
            $this->setParameterAndType($params, $types, $paramsIterator, $show->tvMazeId, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->imdbId, ParameterType::STRING);
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->genres ? implode(',', $show->genres) : null,
                ParameterType::STRING
            );
            $this->setParameterAndType($params, $types, $paramsIterator, $show->status, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->premiered, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->ended, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->officialSite, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->weight, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->networkName, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->networkCountry, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->webChannelName, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->webChannelCountry, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->summary, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->name, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->runtime, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->imageMedium, ParameterType::STRING);
            $this->setParameterAndType($params, $types, $paramsIterator, $show->imageOriginal, ParameterType::STRING);
        }


        $rows = $conn->executeStatement('INSERT INTO shows 
        (tv_maze_id, imdb_id, genres, status, premiered, ended, official_site, 
          weight, network_name, network_country, web_channel_name, web_channel_country,
          summary, name, runtime, image_medium, image_original, created_at, updated_at) 
          VALUES ' . implode(',', $values), $params->toArray(), $types->toArray());


        return (int) $rows;
    }
}
