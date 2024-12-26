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

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            exit('runing migrations failed' . PHP_EOL);
        }
        $this->container = require CONFIG_PATH . '/container/container.php';
    }



    #[DataProvider('dataProvider')]
    public function test_update($updatedShows, $shows, $episodes, $fromFile): void
    {

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get(EntityManager::class);
        $tvMazeService = $this->createStub(TvMazeService::class);
        $tvMazeService->method('getUpdatedShowIDs')->willReturn($updatedShows);
        $tvMazeService->method('getShows')->willReturn($shows);
        $tvMazeService->method('getEpisodes')->willReturn(...$episodes);

        $updateService = new UpdateService(
            new ShowService($entityManager),
            new EpisodeService($entityManager),
            $tvMazeService,
            $entityManager
        );

        $updateService->run();

        $shows = $entityManager->getRepository(Show::class)->findAll();
        $episodes = $entityManager->getRepository(Episode::class)->findAll();
        $this->assertSame('test show 1', $shows[0]->getName());
        if (!$fromFile) {
            $this->assertSame(2, count($shows));
            $this->assertSame(4, count($episodes));
        } else {
            $this->assertSame(200, count($shows));
            $this->assertSame(8000, count($episodes));
        }
    }

    public static function dataProvider(): array
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

        $testUdatedIds10 = range(1, 200);
        $testShows10 = [];
        for ($i = 1; $i <= 200; $i++) {
            $testShows10[] = new ShowData(
                tvMazeId: $i,
                status: 'running',
                weight: 100,
                name: "test show $i",
                summary: "test $i summary",
            );
        }

        $testEpArraysFromFile = [];
        for ($i = 0; $i < 200; $i++) {
            $testEpArraysFromFile[] = array_map(function ($index, $episode) use ($i, $eps) {
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
            }, range(0, count($eps) - 1), $eps);
        }



        return [
            'test simple' => [
                [1, 2],
                $testShowsSimple,
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
                ],
                false
            ],
            'test 10 shows, 5055 eps each' => [
                $testUdatedIds10,
                $testShows10,
                $testEpArraysFromFile,
                true
            ],
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
