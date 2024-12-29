<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\ShowData;
use App\Entity\Show;
use App\Services\Traits\SetParameterAndType;
use ArrayObject;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManager;
use Iterator;
use SplFixedArray;
use Symfony\Component\VarDumper\VarDumper;

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
        $paramNumber = (new ArrayObject($shows[0]))->count();

        $values = array_fill(
            0,
            $showCount,
            "(" . str_repeat('?,', $paramNumber) . "current_timestamp, current_timestamp)"
        );

        $params = new SplFixedArray($showCount * $paramNumber);
        $types = new SplFixedArray($showCount * $paramNumber);
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


    /**
     * Update shows
     *
     * @param array[int]ShowData $shows 
     * @return int number of shows updated
     **/
    public function updateShows(array $shows): void
    {

        if (!$shows) {
            return;
        }

        $conn = $this->entityManager->getConnection();

        $ids = array_keys($shows);
        $paramNumber = (new ArrayObject($shows[array_key_first($shows)]))->count() + 1;

        $cases = [];
        $params = new SplFixedArray(count($ids) * $paramNumber);
        $types = new SplFixedArray(count($ids) * $paramNumber);
        $it = $params->getIterator();
        foreach ($ids as $id) {
            $this->setCase($cases, 'name', $id);
            $this->setCase($cases, 'imdbId', $id);
            $this->setCase($cases, 'genres', $id);
            $this->setCase($cases, 'status', $id);
            $this->setCase($cases, 'premiered', $id);

            // $this->getUpdateCase($premiered, 'premiered', $id, $show->premiered, ParameterType::STRING);
        }

        $this->setParamsFor('name', ParameterType::STRING, $params, $types, $it, $shows);
        $this->setParamsFor('imdbId', ParameterType::STRING, $params, $types, $it, $shows);
        $this->setParamsFor('genres', ParameterType::STRING, $params, $types, $it, $shows);
        $this->setParamsFor('status', ParameterType::STRING, $params, $types, $it, $shows);
        $this->setParamsFor('premiered', ParameterType::STRING, $params, $types, $it, $shows);


        $nameCase = implode(' ', $cases['name']);
        $imdbIdCase = implode(' ', $cases['imdbId']);
        $genresCase = implode(' ', $cases['genres']);
        $statusCase = implode(' ', $cases['status']);
        $premieredCase = implode(' ', $cases['premiered']);


        $this->setParameterAndType($params, $types, $it, $ids, ArrayParameterType::INTEGER);

        $rows = $conn->executeStatement(
            "UPDATE shows 
                SET 
                    name = CASE $nameCase END,          
                    imdb_id = CASE $imdbIdCase END,          
                    genres = CASE $genresCase END,          
                    status = CASE $statusCase END,          
                    premiered = CASE $premieredCase END          
                           
                WHERE id IN (?);",
            $params->toArray(),
            $types->toArray()
        );
    }

    private function setCase(
        array &$cases,
        string $name,
        int $id
    ): void {
        $cases[$name][] = "WHEN id = $id THEN ?";
    }

    private function setParamsFor(
        string $name,
        ParameterType $type,
        SplFixedArray $params,
        SplFixedArray $types,
        Iterator $it,
        array $shows
    ): void {
        foreach ($shows as $show) {
            $value = $show->$name;
            if ($name === 'genres') {
                $value = $show->genres ? implode(',', $show->genres) : null;
            }
            $this->setParameterAndType($params, $types, $it, $value, $type);
        }
    }
}
