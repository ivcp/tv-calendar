<?php

declare(strict_types=1);

error_log(sprintf(
    'ip-check REMOTE=%s CF=%s XFF=%s SRV=%s URI=%s',
    $_SERVER['REMOTE_ADDR'] ?? '-',
    $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '-',
    $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '-',
    $_SERVER['SERVER_ADDR'] ?? '-',
    $_SERVER['REQUEST_URI'] ?? '-'
));

use Slim\App;

$container    = require __DIR__ . '/../bootstrap.php';

$container->get(App::class)->run();
