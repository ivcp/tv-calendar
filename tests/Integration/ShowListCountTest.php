<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\Show;
use App\Enum\Genres;
use App\Services\PaginationService;
use App\Services\ShowService;
use DateTime;
use DI\Container;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

final class ShowListCountTest extends TestCase
{
    private Container $container;
    private EntityManager $entityManager;

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            $this->fail('running migrations failed' . PHP_EOL);
        }

        require_once __DIR__ . '/../../configs/path_constants.php';
        $this->container = require CONFIG_PATH . '/container/container.php';
        $this->entityManager = $this->container->get(EntityManager::class);
    }

    public function testGetShowCountByIdsIgnoresMissingIds(): void
    {
        [$drama, $comedy] = $this->seedShows();

        $this->assertSame(
            2,
            $this->showService()->getShowCountByIds([$drama->getId(), $comedy->getId(), 999999])
        );
    }

    public function testGetShowCountByIdsAppliesTheGenre(): void
    {
        [$drama, $comedy] = $this->seedShows();

        $this->assertSame(
            1,
            $this->showService()->getShowCountByIds(
                [$drama->getId(), $comedy->getId(), 999999],
                Genres::Drama
            )
        );
    }

    public function testGuestPaginationCountsOnlyExistingShows(): void
    {
        [$drama, $comedy] = $this->seedShows();

        $pagination = $this->container->get(PaginationService::class)->showList(
            ['shows' => [(string) $drama->getId(), (string) $comedy->getId(), '999999']],
            null
        );

        $this->assertSame(2, $pagination->getPagination()['showCount']);
        $this->assertSame(1, $pagination->getPagination()['totalPages']);
        $this->assertCount(2, $pagination->getShows());
    }

    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            $this->fail('db teardown failed' . PHP_EOL);
        }
    }

    /**
     * @return array{Show, Show}
     */
    private function seedShows(): array
    {
        $drama = $this->createShow(1001, 'Drama Show', ['Drama']);
        $comedy = $this->createShow(1002, 'Comedy Show', ['Comedy']);

        $this->entityManager->flush();

        return [$drama, $comedy];
    }

    private function createShow(int $tvMazeId, string $name, array $genres): Show
    {
        $show = new Show();
        $show->setTvMazeId($tvMazeId);
        $show->setName($name);
        $show->setGenres($genres);
        $show->setStatus('running');
        $show->setWeight(100);
        $show->setCreatedAt(new DateTime());
        $show->setUpdatedAt(new DateTime());

        $this->entityManager->persist($show);

        return $show;
    }

    private function showService(): ShowService
    {
        return $this->container->get(ShowService::class);
    }
}
