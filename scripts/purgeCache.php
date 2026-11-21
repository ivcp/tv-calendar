<?php

declare(strict_types=1);

use App\Services\CachePurgeService;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../configs/path_constants.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$container = require CONFIG_PATH . '/container/container.php';

$target = $argv[1] ?? 'deploy';
$cachePurgeService = $container->get(CachePurgeService::class);

$result = match ($target) {
    'update' => $cachePurgeService->afterUpdate(),
    'deploy' => $cachePurgeService->afterDeploy(),
    default => null,
};

if ($result === null) {
    fwrite(STDERR, "Usage: php scripts/purgeCache.php [update|deploy]\n");
    exit(1);
}

if (! $result) {
    fwrite(STDERR, "Cache purge failed ($target)\n");
    exit(1);
}

echo "Cache purge completed ($target)\n";
