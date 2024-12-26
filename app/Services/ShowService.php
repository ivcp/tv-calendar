<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\ShowData;
use App\Entity\Show;
use App\Services\Traits\SetParameterAndType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManager;

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

        $values = [];
        for ($i = 1; $i <= count($shows); $i++) {
            $values[] = "(:tvMazeId$i, :imdbId$i, :genres$i, :status$i, :premiered$i, :ended$i, 
            :officialSite$i, :weight$i, :networkName$i, :networkCountry$i, :webChannelName$i, 
            :webChannelCountry$i, :summary$i, :name$i, :runtime$i, :imageMedium$i, 
            :imageOriginal$i, current_timestamp, current_timestamp)";
        }

        $params = [];
        $types = [];
        foreach ($shows as $i => $show) {
            $this->setParameterAndType($params, $types, 'tvMazeId', $i, $show->tvMazeId, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types, 'imdbId', $i, $show->imdbId, ParameterType::STRING);
            $this->setParameterAndType(
                $params,
                $types,
                'genres',
                $i,
                $show->genres ? implode(',', $show->genres) : null,
                ParameterType::STRING
            );
            $this->setParameterAndType($params, $types, 'status', $i, $show->status, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'premiered', $i, $show->premiered, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'ended', $i, $show->ended, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'officialSite', $i, $show->officialSite, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'weight', $i, $show->weight, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types, 'networkName', $i, $show->networkName, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'networkCountry', $i, $show->networkCountry, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'webChannelName', $i, $show->webChannelName, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'webChannelCountry', $i, $show->webChannelCountry, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'summary', $i, $show->summary, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'name', $i, $show->name, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'runtime', $i, $show->runtime, ParameterType::INTEGER);
            $this->setParameterAndType($params, $types, 'imageMedium', $i, $show->imageMedium, ParameterType::STRING);
            $this->setParameterAndType($params, $types, 'imageOriginal', $i, $show->imageOriginal, ParameterType::STRING);
        }


        $rows = $conn->executeStatement('INSERT INTO shows 
        (tv_maze_id, imdb_id, genres, status, premiered, ended, official_site, 
          weight, network_name, network_country, web_channel_name, web_channel_country,
          summary, name, runtime, image_medium, image_original, created_at, updated_at) 
          VALUES ' . implode(',', $values), $params, $types);


        return (int) $rows;
    }
}
