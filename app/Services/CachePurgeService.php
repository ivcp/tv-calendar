<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Contracts\CachePurgeInterface;

/**
 * Decides which pages need invalidating. 
 */
class CachePurgeService
{
    public function __construct(
        private readonly CachePurgeInterface $cache,
        private readonly WebhookService $webhookService,
        private readonly Config $config
    ) {}

    /**
     * The daily TVMaze sync can change the calendar, discover ordering, show
     * pages, and the sitemap.
     */
    public function afterUpdate(): bool
    {
        $paths = $this->cache->purgePaths(['/']);
        $prefixes = $this->cache->purgePrefixes(['/discover', '/shows/', '/sitemap']);

        return $this->reportFailure($paths && $prefixes, 'the TVMaze update');
    }


    public function afterDeploy(): bool
    {
        return $this->reportFailure($this->cache->purgeAll(), 'deploy');
    }

    private function reportFailure(bool $success, string $operation): bool
    {
        if (! $success) {
            $this->webhookService->send(
                (string) $this->config->get('webhook_url', ''),
                "🚨 Cache purge failed after $operation. Check the logs."
            );
        }

        return $success;
    }
}
