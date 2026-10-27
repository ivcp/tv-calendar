<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config;
use App\Contracts\UserInterface;
use App\Enum\AppEnvironment;
use App\Exception\RateLimitException;
use App\Services\RateLimiter;
use App\Services\RequestService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Applies the limits configured under `rate_limit.policies` in configs/app.php.
 *
 * Each policy entry is keyed by "<METHOD> <path>" and holds a list of checks:
 *   ['by' => 'ip'|'email'|'user', 'limit' => int, 'window' => seconds, 'field' => string]
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private const DEFAULT_ERROR_FIELD = 'rate_limit';

    public function __construct(
        private readonly Config $config,
        private readonly RateLimiter $rateLimiter,
        private readonly RequestService $requestService
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! (bool) $this->config->get('rate_limit.enabled', true)) {
            return $handler->handle($request);
        }

        $route    = $request->getMethod() . ' ' . $this->path($request);
        $policies = $this->config->get('rate_limit.policies', []);
        $checks   = is_array($policies) ? ($policies[$route] ?? []) : [];

        if (! is_array($checks) || $checks === []) {
            return $handler->handle($request);
        }

        foreach ($checks as $check) {
            $by     = (string) ($check['by'] ?? '');
            $value  = $this->valueFor($request, $by);
            $limit  = (int) ($check['limit'] ?? 0);
            $window = (int) ($check['window'] ?? 0);

            if ($value === null) {
                continue;
            }

            $result = $this->rateLimiter->consume(
                sprintf('%s:%s:%d:%d:%s', $route, $by, $window, $limit, hash('sha256', $value)),
                $limit,
                $window
            );

            if (! $result->allowed) {
                throw new RateLimitException(
                    (string) ($check['field'] ?? self::DEFAULT_ERROR_FIELD),
                    $result->retryAfter
                );
            }
        }

        return $handler->handle($request);
    }

    private function path(ServerRequestInterface $request): string
    {
        $path = rtrim($request->getUri()->getPath(), '/');

        return $path === '' ? '/' : $path;
    }

    private function valueFor(ServerRequestInterface $request, string $by): ?string
    {
        switch ($by) {
            case 'ip':
                $ip = $this->requestService->getClientIp($request);
                $this->warnOnUnexpectedClientIp($ip);

                return $ip;

            case 'email':
                $body  = $request->getParsedBody();
                $email = is_array($body) ? ($body['email'] ?? null) : null;

                if (! is_string($email) || trim($email) === '') {
                    return null;
                }

                return mb_strtolower(trim($email), 'UTF-8');

            case 'user':
                $user = $request->getAttribute('user');

                return $user instanceof UserInterface ? (string) $user->getId() : null;

            default:
                return null;
        }
    }

    /**
     * In production REMOTE_ADDR must be the visitor address (nginx resolves
     * CF-Connecting-IP). A private/reserved value means the proxy chain changed
     * and every visitor would share the same per-IP bucket.
     */
    private function warnOnUnexpectedClientIp(string $ip): void
    {
        $environment = (string) $this->config->get('app_environment', '');

        if (! AppEnvironment::isProduction($environment)) {
            return;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return;
        }

        error_log(
            'rate-limit: resolved client IP is not a public address (' . $ip . '); per-IP limits are shared'
        );
    }
}
