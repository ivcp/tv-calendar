<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\DataObjects\EpisodeData;
use App\DataObjects\ShowData;
use App\Entity\Episode;
use App\Entity\Show;
use App\Services\EpisodeService;
use App\Services\ShowService;
use App\Services\TvMazeService;
use App\Services\UpdateService;
use DateTime;
use DI\Container;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require __DIR__ . '/../../configs/path_constants.php';

class UpdateTest extends TestCase
{

    private Container $container;
    private EntityManager $entityManager;

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            exit('runing migrations failed' . PHP_EOL);
        }
        $this->container = require CONFIG_PATH . '/container/container.php';
        $this->entityManager =  $this->container->get(EntityManager::class);
    }



    #[DataProvider('insertDataProvider')]
    public function test_update($updatedShows, $shows, $episodes, $fromFile): void
    {
        $tvMazeService = $this->createStub(TvMazeService::class);
        $tvMazeService->method('getUpdatedShowIDs')->willReturn(...$updatedShows);
        $tvMazeService->method('getShows')->willReturn(...$shows);
        $tvMazeService->method('getEpisodes')->willReturn(...$episodes);

        $updateService = new UpdateService(
            new ShowService($this->entityManager),
            new EpisodeService($this->entityManager),
            $tvMazeService,
            $this->entityManager
        );

        $updateService->run();


        $showsInserted = $this->entityManager->createQuery('SELECT COUNT(s) FROM App\Entity\Show s')->getSingleScalarResult();
        $episodesInserted = $this->entityManager->createQuery('SELECT COUNT(e) FROM App\Entity\Episode e')->getSingleScalarResult();

        $firstShow = $this->entityManager->find(Show::class, 1);

        $this->assertSame('test show 1', $firstShow->getName());
        if (!$fromFile) {
            $this->assertSame(2, $showsInserted);
            $this->assertSame(4, $episodesInserted);
            $this->assertSame(2, count($firstShow->getEpisodes()->toArray()));
        } else {
            $this->assertSame(1000, $showsInserted);
            $this->assertSame(40000, $episodesInserted);
            $this->assertSame(40, count($firstShow->getEpisodes()->toArray()));
        }

        $updateService->run();

        $this->entityManager->clear();
        $firstShow = $this->entityManager->find(Show::class, 1);
        $secondShow = $this->entityManager->find(Show::class, 2);

        if (!$fromFile) {
            $this->assertSame('test show update 1', $firstShow->getName());
            $this->assertSame('test show update 2', $secondShow->getName());
            $this->assertContains('Romance', $firstShow->getGenres());
            $this->assertSame('running updated', $firstShow->getStatus());
            $this->assertSame('2024-12-1', $firstShow->getPremiered());
        }
    }


    public static function insertDataProvider(): array
    {

        $episodesFromFile = file_get_contents(__DIR__ . '/episodes.json');
        $eps = json_decode($episodesFromFile);

        $testShowsSimple = [];
        for ($i = 1; $i <= 2; $i++) {
            $testShowsSimple[] = new ShowData(
                tvMazeId: $i,
                status: 'running',
                weight: 100,
                name: "test show $i",
                summary: "test $i summary",
                genres: ['Comedy', 'Drama'],
            );
        }

        $testShowsSimpleUpdate = [];
        for ($i = 1; $i <= 2; $i++) {
            $testShowsSimpleUpdate[] = new ShowData(
                tvMazeId: $i,
                imdbId: "tt$i",
                status: 'running updated',
                weight: 100,
                name: "test show update $i",
                summary: "test $i summary",
                genres: ['Comedy', 'Romance'],
                premiered: '2024-12-1'
            );
        }

        $testUdatedIds1000 = range(1, 1000);
        $testShows1000 = [];
        for ($i = 0; $i < 1000; $i++) {
            $testShows1000[] = new ShowData(
                tvMazeId: $i + 1,
                status: 'running',
                weight: 100,
                name: "test show " . $i + 1,
                summary: "test $i summary",
            );
        }


        $testEpArrays1000 = [];
        for ($i = 0; $i < 1000; $i++) {
            $testEpArrays1000[] = array_map(function ($index, $episode) use ($i, $eps) {
                $epId = count($eps) * $i + $index + 1;
                return new EpisodeData(
                    tvMazeShowId: $i + 1,
                    tvMazeEpisodeId: $epId,
                    episodeName: $episode->name,
                    seasonNumber: $episode?->season,
                    episodeNumber: $episode?->number,
                    episodeSummary: $episode?->summary,
                    type: $episode?->type,
                    airstamp: $episode?->airstamp ? new DateTime($episode->airstamp) : null,
                    runtime: $episode?->runtime,
                    imageMedium: $episode?->image?->medium,
                    imageOriginal: $episode?->image?->original
                );
            }, array_keys($eps), $eps);
        }



        return [
            'test simple' => [
                [[1, 2], [1, 2]],
                [$testShowsSimple, $testShowsSimpleUpdate],
                [
                    [
                        new EpisodeData(
                            tvMazeShowId: 1,
                            tvMazeEpisodeId: 1,
                            episodeName: 'test S1E1',
                            seasonNumber: 1,
                            episodeNumber: 1,
                            episodeSummary: 'summary ep 1',
                            type: 'regular',
                            airstamp: new DateTime('2013-06-25T02:00:00+00:00'),
                            imageMedium: 'img.link',
                            runtime: 60
                        ),
                        new EpisodeData(
                            tvMazeShowId: 1,
                            tvMazeEpisodeId: 2,
                            episodeName: 'test S1E2'
                        )
                    ],
                    [
                        new EpisodeData(
                            tvMazeShowId: 2,
                            tvMazeEpisodeId: 3,
                            episodeName: 'test S2E1'
                        ),
                        new EpisodeData(
                            tvMazeShowId: 2,
                            tvMazeEpisodeId: 4,
                            episodeName: 'test S2E2'
                        )
                    ],
                    //  ep to update
                    [
                        new EpisodeData(
                            tvMazeShowId: 1,
                            tvMazeEpisodeId: 1,
                            episodeName: 'updated S1E1',
                            seasonNumber: 1,
                            episodeNumber: 1,
                            episodeSummary: 'summary ep 1',
                            type: 'regular',
                            airstamp: new DateTime('2013-06-25T02:00:00+00:00'),
                            imageMedium: 'img.link',
                            runtime: 60
                        )
                    ],
                ],
                false
            ],
            // 'test 1000 shows, 40 eps each' => [
            //     $testUdatedIds1000,
            //     $testShows1000,
            //     $testEpArrays1000,
            //     true
            // ],
        ];
    }

    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            exit('db teardown failed' . PHP_EOL);
        }
    }
}
