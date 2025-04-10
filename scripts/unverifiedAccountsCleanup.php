<?php

declare(strict_types=1);

use App\Services\AccountCleanupService;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../configs/path_constants.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = require CONFIG_PATH . '/container/container.php';

$container->get(AccountCleanupService::class)->run();
