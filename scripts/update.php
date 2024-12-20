<?php

declare(strict_types=1);

use App\Services\ShowService;
use App\Services\TvMazeService;
use Doctrine\ORM\EntityManager;
use Dotenv\Dotenv;
use GuzzleHttp\Client;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../configs/path_constants.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container      = require CONFIG_PATH . '/container/container.php';

// $show = (new ShowService($container->get(EntityManager::class)))->getById(1);
// var_dump($show->getSummary());

$tvMazeService = new TvMazeService($container->get(Client::class));

// $updated = $tvMazeService->getUpdatedShows();

var_dump($tvMazeService->getShow(1));
