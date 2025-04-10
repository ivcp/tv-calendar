<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\ShowData;
use App\Entity\Show;
use App\Entity\User;
use App\Entity\UserShows;
use App\Enum\DiscoverSort;
use App\Enum\Genres;
use App\Enum\ShowListSort;
use App\Services\Traits\ParamsTypesCases;
use ArrayObject;
use BackedEnum;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManager;
use Exception;
use SplFixedArray;

class ShowService
{
    use ParamsTypesCases;

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function getById(int $id): ?Show
    {
        return $this->entityManager->find(Show::class, $id);
    }

    public function getImageOriginal(int $id): ?string
    {
        return $this->entityManager->getRepository(Show::class)
        ->createQueryBuilder('c')
        ->select('c.imageOriginal')
        ->where('c.id = :id')
        ->setParameter('id', $id)
        ->getQuery()
        ->getSingleResult()['imageOriginal'];
    }

    public function getShowsByTvMazeId(array $ids): array
    {
        return $this->entityManager->getRepository(Show::class)->findBy(['tvMazeId' => $ids]);
    }

    public function getShowCount(BackedEnum $genre = Genres::Default, ?string $query = null): int
    {
        $repository = $this->entityManager->getRepository(Show::class);
        if ($genre === Genres::Default && !$query) {
            return $repository->count();
        }

        $qb = $repository->createQueryBuilder('c')
        ->select('count(distinct c)');

        if ($genre === Genres::Default && $query) {
            $qb->where('lower(c.name) LIKE :query')
            ->setParameter('query', '%' . strtolower($query) . '%');
        }

        if ($genre !== Genres::Default) {
            $qb->where('c.genres LIKE :genre')
            ->setParameter('genre', '%' . $genre->value . '%');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }


    /**
     * Get shows, paginated
     *
     * @return Show[]
     **/
    public function getPaginatedShows(
        int $start,
        int $length,
        BackedEnum $sort,
        BackedEnum $genre,
        ?User $user,
        ?string $query,
        array $localList
    ): array {
        $qb = $this->entityManager->getRepository(Show::class)
                ->createQueryBuilder('c')
                ->setFirstResult($start)
                ->setMaxResults($length);

        if ($user) {
            $qb = $this->entityManager->getRepository(UserShows::class)->createQueryBuilder('c');
            $qb->select('s')
            ->where($qb->expr()->eq('c.user', ':userId'))
            ->innerJoin(Show::class, 's', 'WITH', 's.id = c.show')
            ->setFirstResult($start)
            ->setMaxResults($length)
            ->setParameter('userId', $user->getId());
        }

        if (!$user && !$query && count($localList) > 0) {
            $qb
            ->where('c.id IN (:list)')
            ->setParameter('list', $localList);
        }

        switch ($sort) {
            case DiscoverSort::New:
                $qb->andWhere('c.premiered IS NOT NULL');
                $qb->addOrderBy('c.premiered', 'desc');
                break;
            case DiscoverSort::Popular:
                $qb->addOrderBy('c.weight', 'desc');
                $qb->addOrderBy('c.updatedAt', 'desc');
                break;
            case ShowListSort::Added:
                $qb->addOrderBy('c.createdAt', 'desc');
                break;
            case ShowListSort::Alphabetical:
                $qb->addOrderBy('s.name', 'asc');
                break;
            case ShowListSort::Popular:
                $qb->addOrderBy('s.weight', 'desc');
                $qb->addOrderBy('s.updatedAt', 'desc');
                break;
            case ShowListSort::New:
                $qb->andWhere('s.premiered IS NOT NULL');
                $qb->addOrderBy('s.premiered', 'desc');
                break;
        }

        if ($genre !== Genres::Default) {
            $i = $user ? 's' : 'c';
            $qb->andWhere("$i.genres LIKE :genre")->setParameter('genre', '%' . $genre->value . '%');
        }

        if ($query) {
            $qb
            ->select('c.id, c.name')
            ->where('lower(c.name) LIKE :query')
            ->setParameter('query', '%' . strtolower($query) . '%');
        }

        $qb->addOrderBy($user ? 's.id' : 'c.id', 'desc');


        return $qb->getQuery()->getResult();
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
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->tvMazeId,
                ParameterType::INTEGER
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->imdbId,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->genres ? implode(',', $show->genres) : null,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->status,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->premiered,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->ended,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->officialSite,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->weight,
                ParameterType::INTEGER
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->networkName,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->networkCountry,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->webChannelName,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->webChannelCountry,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->summary,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->name,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->runtime,
                ParameterType::INTEGER
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->imageMedium,
                ParameterType::STRING
            );
            $this->setParameterAndType(
                $params,
                $types,
                $paramsIterator,
                $show->imageOriginal,
                ParameterType::STRING
            );
        }

        try {
            $rows = $conn->executeStatement('INSERT INTO shows 
            (tv_maze_id, imdb_id, genres, status, premiered, ended, official_site, 
            weight, network_name, network_country, web_channel_name, web_channel_country,
            summary, name, runtime, image_medium, image_original, created_at, updated_at) 
          VALUES ' . implode(',', $values), $params->toArray(), $types->toArray());
        } catch (Exception $e) {
            throw $e;
        }

        return (int) $rows;
    }


    /**
     * Update shows
     *
     * @param array[int]ShowData $shows
     * @return int number of shows updated
     **/
    public function updateShows(array $shows): int
    {

        if (!$shows) {
            return 0;
        }

        $conn = $this->entityManager->getConnection();

        $ids = array_keys($shows);
        $updatebles = [
            'name' => ParameterType::STRING,
            'imdbId' => ParameterType::STRING,
            'genres' => ParameterType::STRING,
            'status' => ParameterType::STRING,
            'premiered' => ParameterType::STRING,
            'ended' => ParameterType::STRING,
            'officialSite' => ParameterType::STRING,
            'weight' => ParameterType::INTEGER,
            'networkName' => ParameterType::STRING,
            'networkCountry' => ParameterType::STRING,
            'webChannelName' => ParameterType::STRING,
            'webChannelCountry' => ParameterType::STRING,
            'summary' => ParameterType::STRING,
            'runtime' => ParameterType::INTEGER,
            'imageMedium' => ParameterType::STRING,
            'imageOriginal' => ParameterType::STRING
        ];

        $cases = [];
        $params = new SplFixedArray(count($ids) * count($updatebles) + 1);
        $types = new SplFixedArray(count($ids) * count($updatebles) + 1);
        $it = $params->getIterator();

        foreach ($updatebles as $updatable => $type) {
            $this->setCaseAndParams($updatable, $type, $params, $types, $it, $shows, $cases);
        }

        [
            $nameCase,
            $imdbIdCase,
            $genresCase,
            $statusCase,
            $premieredCase,
            $endedCase,
            $officialSiteCase,
            $weightCase,
            $networkNameCase,
            $networkCountryCase,
            $webChannelNameCase,
            $webChannelCountryCase,
            $summaryCase,
            $runtimeCase,
            $imageMediumCase,
            $imageOriginalCase
        ] = array_map(function ($updatable) use ($cases) {
            return implode(' ', $cases[$updatable]);
        }, array_keys($updatebles));


        $this->setParameterAndType($params, $types, $it, $ids, ArrayParameterType::INTEGER);

        try {
            $rows = $conn->executeStatement(
                "UPDATE shows 
                    SET 
                        name = CASE $nameCase END,          
                        imdb_id = CASE $imdbIdCase END,          
                        genres = CASE $genresCase END,          
                        status = CASE $statusCase END,          
                        premiered = CASE $premieredCase END,          
                        ended = CASE $endedCase END,          
                        official_site = CASE $officialSiteCase END,
                        weight = CASE $weightCase END,
                        network_name = CASE $networkNameCase END,
                        network_country = CASE $networkCountryCase END,
                        web_channel_name = CASE $webChannelNameCase END,
                        web_channel_country = CASE $webChannelCountryCase END,
                        summary = CASE $summaryCase END,
                        runtime = CASE $runtimeCase END,
                        image_medium = CASE $imageMediumCase END,
                        image_original = CASE $imageOriginalCase END,
                        updated_at = current_timestamp
                    WHERE id IN (?);",
                $params->toArray(),
                $types->toArray()
            );
        } catch (Exception $e) {
            throw $e;
        }

        return (int) $rows;
    }
}
