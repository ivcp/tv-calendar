<?php

declare(strict_types=1);

use App\Services\ShowCleanupService;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../configs/path_constants.php';

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo <<<USAGE
    Usage: php scripts/cleanupShows.php [--apply]

    Compares local shows with TVMaze's live show list and removes shows whose
    TVMaze ID no longer exists, together with their episodes and subscriptions.

      (no flag)  dry run: report what would be deleted
      --apply    actually delete the confirmed dead shows

    USAGE;
    exit(0);
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$container = require CONFIG_PATH . '/container/container.php';

$apply = in_array('--apply', $argv, true);

$stats = $container->get(ShowCleanupService::class)->run(dryRun: !$apply);

exit($stats['errors'] > 0 ? 1 : 0);
