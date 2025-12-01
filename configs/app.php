<?php

declare(strict_types=1);

use App\Enum\AppEnvironment;

$appEnv = $_ENV['APP_ENV'] ?? AppEnvironment::Production->value;
$appName = strtolower(str_replace(' ', '_', $_ENV['APP_NAME']));

return [
    'app_name'              => $_ENV['APP_NAME'],
    'app_url'              => $_ENV['APP_URL'],
    'app_key'              => $_ENV['APP_KEY'] ?? '',
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
        'flash_name' => $appName . '_flash',
        'secure' => AppEnvironment::isDevelopment($appEnv) ? false : true,
        'httponly' => true,
        'samesite' => 'lax',
        'lifetime' =>  60 * 60 * 24 * 365
    ],
    'json_tags' => JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_THROW_ON_ERROR,
    'popular_weight' => 99,
    'mailer' => [
        'dsn' => $_ENV['MAILER_DSN'],
        'from' => $_ENV['MAILER_FROM']
    ],
    'oauth' => [
        'google' => [
            'client_id' => $_ENV['GOOGLE_CLIENT_ID'],
            'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'],
            'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI']
        ]
    ],
    'turnstile' => [
        'site_key' => $_ENV['TURNSTILE_SITE_KEY'] ?? '',
        'secret_key' => $_ENV['TURNSTILE_SECRET_KEY'] ?? '',
    ],
    'ntfy' => [
        'base_url' => $_ENV['NTFY_BASE_URL'],
        'admin_token' => $_ENV['NTFY_ADMIN_TOKEN'],
    ]
];
