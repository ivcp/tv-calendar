<?php

declare(strict_types=1);

use App\Services\CachePurgeService;
use App\Services\UpdateService;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../configs/path_constants.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$container = require CONFIG_PATH . '/container/container.php';

$updateService = $container->get(UpdateService::class);
$cachePurgeService = $container->get(CachePurgeService::class);

try {
    $updateService->run();
} finally {
    $cachePurgeService->afterUpdate();
}
