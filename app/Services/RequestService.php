<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

class RequestService
{
    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function getReferer(ServerRequestInterface $request): string
    {
        $referer = $request->getHeader('referer')[0] ?? '';

        if (! $referer) {
            return $this->session->get('previousUrl');
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);

        if ($refererHost !== $request->getUri()->getHost()) {
            $referer =  $this->session->get('previousUrl');
        }

        return $referer;
    }

    public function isXhr(ServerRequestInterface $request): bool
    {
        return $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * The client address as forwarded by nginx, which already resolves
     * CF-Connecting-IP for requests coming through the proxy (real_ip module).
     * Do not read X-Forwarded-For/CF-Connecting-IP here: that would allow
     * spoofing when the app is reached directly.
     */
    public function getClientIp(ServerRequestInterface $request): string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';

        return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
    }
}
