<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\EpisodeService;
use App\Services\ShowService;
use App\Services\TvMazeService;
use App\Services\UpdateService;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class UpdateTest extends TestCase
{

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            exit('runing migrations failed' . PHP_EOL);
        }
    }

    public function test_update(): void
    {

        require __DIR__ . '/../../configs/path_constants.php';
        $container = require CONFIG_PATH . '/container/container.php';

        $entityManager = $container->get(EntityManager::class);
        $tvMazeService = $this->createConfiguredStub(
            TvMazeService::class,
            [
                'getUpdatedShows'     => ['foo'],
            ]
        );
        $updateService = new UpdateService(
            new ShowService($entityManager),
            new EpisodeService($entityManager),
            $tvMazeService
        );

        $updateService->run();
    }


    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            exit('db teardown failed' . PHP_EOL);
        }
    }
}
