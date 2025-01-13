<?php

declare(strict_types=1);

use App\Enum\AppEnvironment;

$appEnv = $_ENV['APP_ENV'] ?? AppEnvironment::Production->value;
$appName = strtolower(str_replace(' ', '_', $_ENV['APP_NAME']));

return [
    'app_name'              => $_ENV['APP_NAME'],
    'app_version'           => $_ENV['APP_VERSION'] ?? '1.0',
    'app_environment'       => $appEnv,
    'display_error_details' => (bool) ($_ENV['APP_DEBUG'] ?? 0),
    'log_errors'            => true,
    'log_error_details'     => true,
    'doctrine'              => [
        'dev_mode'   => AppEnvironment::isDevelopment($appEnv),
        'cache_dir'  => STORAGE_PATH . '/cache/doctrine',
        'entity_dir' => [APP_PATH . '/Entity'],
        'connection' => [
            'driver'   => $_ENV['DB_DRIVER'] ?? 'pdo_pgsql',
            'host'     => $_ENV['DB_HOST'] ?? 'db',
            'port'     => $_ENV['DB_PORT'] ?? 5432,
            'dbname'   => $_ENV['DB_NAME'],
            'user'     => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASS'],
        ],
    ],
    'client' => [
        'retries' => 3,
        'timeout' => 3.0
    ],
    'session' => [
        'name' => $appName . '_session',
        'name' => $appName . '_flash',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'lax',
    ]
];
