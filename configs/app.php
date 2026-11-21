<?php

declare(strict_types=1);

use App\Enum\AppEnvironment;

$appEnv = $_ENV['APP_ENV'] ?? AppEnvironment::Production->value;
$appName = strtolower(str_replace(' ', '_', $_ENV['APP_NAME']));

$appHost = parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST);
$cloudflareHosts = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) ($_ENV['CLOUDFLARE_HOSTS'] ?? ''))
)));

if ($cloudflareHosts === [] && is_string($appHost) && $appHost !== '') {
    $cloudflareHosts = [
        $appHost,
        str_starts_with($appHost, 'www.') ? substr($appHost, 4) : 'www.' . $appHost,
    ];
    $cloudflareHosts = array_values(array_unique($cloudflareHosts));
}

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
        'proxy_dir'  => STORAGE_PATH . '/cache/doctrine/proxies',
        'entity_dir' => [APP_PATH . '/Entity'],
        'connection' => [
            'driver'   => $_ENV['DB_DRIVER'] ?? 'pdo_pgsql',
            'host'     => $_ENV['DB_HOST'] ?? 'tv_db',
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
    'tvmaze' => [
        'user_agent' => sprintf(
            '%s/%s',
            $_ENV['APP_NAME'] ?? 'tv-calendar',
            $_ENV['APP_VERSION'] ?? '1.0'
        ) . (($_ENV['APP_URL'] ?? '') !== '' ? ' (+' . $_ENV['APP_URL'] . ')' : ''),
        'timeout' => 10.0,
    ],
    'session' => [
        'name' => $appName . '_session',
        'flash_name' => $appName . '_flash',
        'secure' => AppEnvironment::isDevelopment($appEnv) ? false : true,
        'httponly' => true,
        'samesite' => 'lax',
        // Session only; "remember me" is a separate, longer-lived signed cookie.
        'lifetime' => 60 * 60 * 4
    ],
    'remember' => [
        'cookie_name' => $appName . '_remember',
        // Fixed lifetime: a successful auto-login never extends it.
        'lifetime' => 60 * 60 * 24 * 180,
        // The browser keeps the (already invalid) cookie for this much longer,
        // so a lapsed user can be sent to the login page instead of silently
        // becoming a guest. Purely a UX hint: the signed expiry above decides
        // whether the cookie still authenticates anyone.
        'expiry_grace' => 60 * 60 * 24 * 30,
        'secure' => AppEnvironment::isDevelopment($appEnv) ? false : true,
        'httponly' => true,
        'samesite' => 'lax'
    ],
    'json_tags' => JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_THROW_ON_ERROR,
    'popular_weight' => 99,
    'premieres_weight' => 70,
    // Unverified accounts get one reminder deletion_notice_lead_hours before
    // they are removed by scripts/unverifiedAccountsCleanup.php, and are only
    // removed once that reminder has been sent. The first reminder goes out
    // unverified_ttl_days minus the lead time after registration, so a missed
    // cleanup run delays deletion instead of skipping the warning.
    'account' => [
        'unverified_ttl_days'        => 5,
        'deletion_notice_lead_hours' => 48,
    ],
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
    'cloudflare' => [
        'zone_id' => $_ENV['CLOUDFLARE_ZONE_ID'] ?? '',
        'api_token' => $_ENV['CLOUDFLARE_API_TOKEN'] ?? '',
        'hosts' => $cloudflareHosts,
    ],
    'ntfy' => [
        'base_url' => $_ENV['NTFY_BASE_URL'],
        'admin_token' => $_ENV['NTFY_ADMIN_TOKEN'],
    ],
    'webhook_url' => $_ENV['WEBHOOK_URL'] ?? '',
    'url_secret_key' => $_ENV['URL_SECRET_KEY'],
    // Application-level rate limits, enforced by App\Middleware\RateLimitMiddleware.
    // Keys are "<METHOD> <path>", each entry is a list of checks with:
    //   by: ip|email|user, limit: max requests, window: seconds, field: form error key.
    'rate_limit' => [
        'enabled' => true,
        'policies' => [
            'POST /login' => [
                ['by' => 'ip', 'limit' => 40, 'window' => 900, 'field' => 'password'],
                ['by' => 'email', 'limit' => 20, 'window' => 900, 'field' => 'password'],
            ],
            'POST /register' => [
                ['by' => 'ip', 'limit' => 5, 'window' => 3600, 'field' => 'email'],
            ],
            'POST /forgot-password' => [
                ['by' => 'ip', 'limit' => 10, 'window' => 3600, 'field' => 'email'],
                ['by' => 'email', 'limit' => 5, 'window' => 3600, 'field' => 'email'],
                ['by' => 'email', 'limit' => 1, 'window' => 60, 'field' => 'email'],
            ],
            'POST /verify' => [
                ['by' => 'user', 'limit' => 5, 'window' => 3600],
                ['by' => 'user', 'limit' => 1, 'window' => 60],
            ],
            'GET /search' => [
                ['by' => 'ip', 'limit' => 60, 'window' => 60],
            ],
            'POST /ntfy-test' => [
                ['by' => 'user', 'limit' => 1, 'window' => 15],
                ['by' => 'user', 'limit' => 10, 'window' => 3600],
            ],
            'POST /discord-test' => [
                ['by' => 'user', 'limit' => 1, 'window' => 15],
                ['by' => 'user', 'limit' => 10, 'window' => 3600],
            ],
        ],
    ],
];
