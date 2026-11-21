<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Contracts\CachePurgeInterface;
use App\Enum\AppEnvironment;
use GuzzleHttp\Client;

class CloudflareCacheService implements CachePurgeInterface
{
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private readonly Client $client,
        private readonly Config $config
    ) {
    }

    public function purgeAll(): bool
    {
        if (! $this->ensureConfigured()) {
            return false;
        }

        return $this->purge(['hosts' => $this->hosts()]);
    }

    public function purgePaths(array $paths): bool
    {
        if (! $this->ensureConfigured()) {
            return false;
        }

        $files = [];

        foreach ($this->hosts() as $host) {
            foreach ($paths as $path) {
                $files[] = 'https://' . $host . $this->asPath($path);
            }
        }

        return $files !== [] && $this->purge(['files' => $files]);
    }

    public function purgePrefixes(array $prefixes): bool
    {
        if (! $this->ensureConfigured()) {
            return false;
        }

        $values = [];

        foreach ($this->hosts() as $host) {
            foreach ($prefixes as $prefix) {
                $values[] = $host . $this->asPath($prefix);
            }
        }

        return $values !== [] && $this->purge(['prefixes' => $values]);
    }

    private function purge(array $payload): bool
    {
        $zoneId = (string) $this->config->get('cloudflare.zone_id');
        $apiToken = (string) $this->config->get('cloudflare.api_token');

        try {
            $response = $this->client->post(
                self::API_BASE . '/zones/' . rawurlencode($zoneId) . '/purge_cache',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiToken,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'http_errors' => false,
                    'timeout' => 10.0,
                ]
            );

            $body = json_decode((string) $response->getBody(), true);

            if (
                $response->getStatusCode() !== 200
                || ! is_array($body)
                || ($body['success'] ?? false) !== true
            ) {
                error_log(
                    'Cloudflare cache purge failed: ' .
                    json_encode(is_array($body) ? ($body['errors'] ?? []) : $response->getReasonPhrase())
                );

                return false;
            }
        } catch (\Throwable $e) {
            error_log('Cloudflare cache purge failed: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    private function ensureConfigured(): bool
    {
        if (
            (string) $this->config->get('cloudflare.zone_id') !== ''
            && (string) $this->config->get('cloudflare.api_token') !== ''
            && $this->hosts() !== []
        ) {
            return true;
        }

        if (AppEnvironment::isProduction((string) $this->config->get('app_environment'))) {
            error_log(
                'Cloudflare cache purge skipped: CLOUDFLARE_ZONE_ID, ' .
                'CLOUDFLARE_API_TOKEN and CLOUDFLARE_HOSTS must be configured.'
            );
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function hosts(): array
    {
        $hosts = $this->config->get('cloudflare.hosts', []);

        if (! is_array($hosts)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn($host) => trim((string) $host), $hosts),
            static fn(string $host) => $host !== ''
        )));
    }

    private function asPath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }
}
