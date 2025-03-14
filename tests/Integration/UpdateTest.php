<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Config;
use App\DataObjects\EpisodeData;
use App\DataObjects\ShowData;
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

class UpdateTest extends TestCase
{
    private Container $container;
    private EntityManager $entityManager;
    private Config $config;

    public function setUp(): void
    {

        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            $this->fail('runing migrations failed' . PHP_EOL);
        }
        require_once __DIR__ . '/../../configs/path_constants.php';
        $this->container = require CONFIG_PATH . '/container/container.php';
        $this->entityManager =  $this->container->get(EntityManager::class);
        $this->config = $this->container->get(Config::class);
    }



    #[DataProvider('dataProvider')]
    public function testUpdateMain($updatedShows, $shows, $episodes, $fromFile): void
    {
        $tvMazeService = $this->createStub(TvMazeService::class);
        $tvMazeService->method('getUpdatedShowIDs')->willReturn(...$updatedShows);
        $tvMazeService->method('getShows')->willReturn(...$shows);
        $tvMazeService->method('getEpisodes')->willReturn(...$episodes);

        $updateService = new UpdateService(
            new ShowService($this->entityManager),
            new EpisodeService($this->entityManager, $this->config),
            $tvMazeService,
            $this->entityManager
        );

        [
            $showInsertCount,
            $epInsertCount,
            $showUpdatedCount,
            $epUpdatedCount,
            $epRemovedCount
        ] = $updateService->run();


        $showsInserted = $this->entityManager
            ->createQuery('SELECT COUNT(s) FROM App\Entity\Show s')
            ->getSingleScalarResult();
        $episodesInserted = $this->entityManager
            ->createQuery('SELECT COUNT(e) FROM App\Entity\Episode e')
            ->getSingleScalarResult();

        $firstShow = $this->entityManager->find(Show::class, 1);

        $this->assertSame('test show 1', $firstShow->getName());
        if (!$fromFile) {
            $this->assertSame(2, $showsInserted);
            $this->assertSame(2, $showInsertCount);
            $this->assertSame(4, $episodesInserted);
            $this->assertSame(4, $epInsertCount);
            $this->assertSame(0, $showUpdatedCount);
            $this->assertSame(0, $epUpdatedCount);
            $this->assertSame(0, $epRemovedCount);
            $this->assertSame(2, $firstShow->getEpisodes()->count());
        } else {
            $this->assertSame(1000, $showsInserted);
            $this->assertSame(40000, $episodesInserted);
            $this->assertSame(40, $firstShow->getEpisodes()->count());
            $this->assertSame(1000, $showInsertCount);
            $this->assertSame(40000, $episodesInserted);
        }

        [
            $showInsertCount,
            $epInsertCount,
            $showUpdatedCount,
            $epUpdatedCount,
            $epRemovedCount
        ] = $updateService->run();

        $this->entityManager->clear();
        if (!$fromFile) {
            $this->assertSame(1, $showInsertCount);
            $this->assertSame(2, $showUpdatedCount);
            $this->assertSame(2, $epInsertCount);
            $this->assertSame(2, $epUpdatedCount);
            $this->assertSame(2, $epRemovedCount);
            $firstShow = $this->entityManager->find(Show::class, 1);
            $secondShow = $this->entityManager->find(Show::class, 2);
            $thirdShow = $this->entityManager->find(Show::class, 3);
            $this->assertNotNull($thirdShow);
            $this->assertSame('test show update 1', $firstShow->getName());
            $this->assertSame('test show update 2', $secondShow->getName());
            $this->assertContains('Romance', $firstShow->getGenres());
            $this->assertSame('running updated', $firstShow->getStatus());
            $this->assertSame('2024-12-1', $firstShow->getPremiered());
            $this->assertSame('2024-12-31', $firstShow->getEnded());
            $this->assertSame('www.updated.com', $firstShow->getOfficialSite());
            $this->assertSame(99, $firstShow->getWeight());
            $this->assertSame('update network name', $firstShow->getNetworkName());
            $this->assertSame('update network country', $firstShow->getNetworkCountry());
            $this->assertSame('update web ch name', $firstShow->getWebChannelName());
            $this->assertSame('update web ch country', $firstShow->getWebChannelCountry());
            $this->assertSame('update 1 summary', $firstShow->getSummary());
            $this->assertSame(60, $firstShow->getRuntime());
            $this->assertSame('image.medium', $firstShow->getImageMedium());
            $this->assertSame('image.original', $firstShow->getImageOriginal());
            $this->assertSame(2, $firstShow->getEpisodes()->count());
            $this->assertSame('updated E1', $firstShow->getEpisodes()->first()->getName());
            $this->assertSame('updated E4', $secondShow->getEpisodes()
                ->findFirst(fn ($k, $v) => $v->getId() === 4)->getName());
            $this->assertSame(6, $firstShow->getEpisodes()->first()->getSeason());
            $this->assertSame(12, $firstShow->getEpisodes()->first()->getNumber());
            $this->assertEqualsWithDelta(
                (new DateTime('now'))->getTimestamp(),
                $firstShow->getEpisodes()->first()->getAirstamp()->getTimestamp(),
                5
            );
            $this->assertSame('special', $secondShow->getEpisodes()->last()->getType());
            $this->assertSame('summary ep 1 updated', $firstShow->getEpisodes()->first()->getSummary());
            $this->assertSame(120, $firstShow->getEpisodes()->first()->getRuntime());
            $this->assertSame('img.medium.update', $firstShow->getEpisodes()->first()->getImageMedium());
            $this->assertSame('img.original.update', $secondShow->getEpisodes()->last()->getImageOriginal());
            $this->assertSame('test E5', $firstShow->getEpisodes()->last()->getName());
            $this->assertSame(2, $secondShow->getEpisodes()->count());
            $this->assertEqualsWithDelta(
                (new DateTime('now'))->getTimestamp(),
                $firstShow->getUpdatedAt()->getTimestamp(),
                1
            );
        } else {
            $this->assertSame(1000, $epUpdatedCount);
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

        $testShowsSimpleUpdate = [];
        for ($i = 1; $i <= 3; $i++) {
            $testShowsSimpleUpdate[] = new ShowData(
                tvMazeId: $i,
                imdbId: "tt$i",
                status: 'running updated',
                weight: 99,
                name: "test show update $i",
                summary: "update $i summary",
                genres: ['Comedy', 'Romance'],
                premiered: '2024-12-1',
                ended: '2024-12-31',
                officialSite: 'www.updated.com',
                networkName: 'update network name',
                networkCountry: 'update network country',
                webChannelName: 'update web ch name',
                webChannelCountry: 'update web ch country',
                runtime: 60,
                imageMedium: 'image.medium',
                imageOriginal: 'image.original',
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
                $airstamp = $index === 39 ? 'now' : $episode?->airstamp;
                return new EpisodeData(
                    tvMazeShowId: $i + 1,
                    tvMazeEpisodeId: $epId,
                    episodeName: $episode->name,
                    seasonNumber: $episode?->season,
                    episodeNumber: $episode?->number,
                    episodeSummary: $episode?->summary,
                    type: $episode?->type,
                    airstamp: $airstamp ? new DateTime($airstamp) : null,
                    runtime: $episode?->runtime,
                    imageMedium: $episode?->image?->medium,
                    imageOriginal: $episode?->image?->original
                );
            }, array_keys($eps), $eps);
        }




        return [
            'test simple' => [
                [[1, 2], [1, 2, 3]],
                [$testShowsSimple, $testShowsSimpleUpdate],
                [
                    [
                        new EpisodeData(
                            tvMazeShowId: 1,
                            tvMazeEpisodeId: 1,
                            episodeName: 'test E1',
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
                            episodeName: 'test E2'
                        )
                    ],
                    [
                        new EpisodeData(
                            tvMazeShowId: 2,
                            tvMazeEpisodeId: 3,
                            episodeName: 'test E3'
                        ),
                        new EpisodeData(
                            tvMazeShowId: 2,
                            tvMazeEpisodeId: 4,
                            episodeName: 'test E4'
                        )
                    ],
                    //no eps for 3rd show
                    [],
                    //  EPS TO UPDATE
                    [
                        new EpisodeData(
                            tvMazeShowId: 1,
                            tvMazeEpisodeId: 1,
                            episodeName: 'updated E1',
                            seasonNumber: 6,
                            episodeNumber: 12,
                            episodeSummary: 'summary ep 1 updated',
                            type: 'regular',
                            airstamp: new DateTime('now'),
                            imageMedium: 'img.medium.update',
                            runtime: 120
                        ),
                        //new episode, EP2 removed
                        new EpisodeData(
                            tvMazeShowId: 1,
                            tvMazeEpisodeId: 5,
                            episodeName: 'test E5'
                        )
                    ],
                    [
                        //remove E3, update E4, add E6
                        new EpisodeData(
                            tvMazeShowId: 2,
                            tvMazeEpisodeId: 4,
                            episodeName: 'updated E4',
                            seasonNumber: 1,
                            episodeNumber: 1,
                            episodeSummary: 'summary ep 4',
                            type: 'special',
                            airstamp: new DateTime('now'),
                            imageOriginal: 'img.original.update',
                            runtime: 60
                        ),
                        new EpisodeData(
                            tvMazeShowId: 2,
                            tvMazeEpisodeId: 6,
                            episodeName: 'updated E6',
                            seasonNumber: 1,
                            episodeNumber: 6,
                            episodeSummary: 'summary',
                            type: 'special',
                            airstamp: new DateTime('now'),
                            imageOriginal: 'img.original.update',
                            runtime: 60
                        )
                    ],
                ],
                false
            ],
            'test 1000 shows, 40 eps each' => [
                [$testUdatedIds1000, $testUdatedIds1000],
                [$testShows1000, $testShows1000],
                [...$testEpArrays1000, ...$testEpArrays1000],
                true
            ],
        ];
    }

    #[DataProvider('dataProviderHuge')]
    public function testUpdateHuge($updatedShows, $shows, $episodes): void
    {
        $tvMazeService = $this->createStub(TvMazeService::class);
        $tvMazeService->method('getUpdatedShowIDs')->willReturn(...$updatedShows);
        $tvMazeService->method('getShows')->willReturn(...$shows);
        $tvMazeService->method('getEpisodes')->willReturn(...$episodes);

        $updateService = new UpdateService(
            new ShowService($this->entityManager),
            new EpisodeService($this->entityManager, $this->config),
            $tvMazeService,
            $this->entityManager
        );

        [
            $showInsertCount,
            $epInsertCount,
            $showUpdatedCount,
            $epUpdatedCount,
            $epRemovedCount
        ] = $updateService->run();

        $this->assertSame(1, $showInsertCount);
        $this->assertSame(9215, $epInsertCount);
        $this->assertSame(0, $showUpdatedCount);
        $this->assertSame(0, $epUpdatedCount);
        $this->assertSame(0, $epRemovedCount);

        [
            $showInsertCount,
            $epInsertCount,
            $showUpdatedCount,
            $epUpdatedCount,
            $epRemovedCount
        ] = $updateService->run();
        $this->assertSame(0, $epInsertCount);
        $this->assertSame(0, $epRemovedCount);
    }

    public static function dataProviderHuge(): array
    {

        $episodesFromFile = file_get_contents(__DIR__ . '/episodes_huge.json');
        $eps = json_decode($episodesFromFile);

        $testShow = new ShowData(
            tvMazeId: 1,
            status: 'running',
            weight: 100,
            name: "test show 1",
            summary: "test summary",
            genres: ['Comedy', 'Drama']
        );

        $testEps = array_map(function ($episode) {
            return new EpisodeData(
                tvMazeShowId: 1,
                tvMazeEpisodeId: $episode->id,
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
        }, $eps);

        return [
            [
                [[1], [1]],
                [[$testShow], [$testShow]],
                [$testEps, $testEps]
            ],
        ];
    }

    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            $this->fail('db teardown failed' . PHP_EOL);
        }
    }
}
