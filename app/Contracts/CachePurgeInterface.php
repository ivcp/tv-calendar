<?php

declare(strict_types=1);

namespace App\Contracts;

interface CachePurgeInterface
{
    /**
     * Purge every cached response for the configured site hosts.
     */
    public function purgeAll(): bool;

    /**
     * Purge exact, site-relative paths such as "/" or "/favicon.ico".
     *
     * @param string[] $paths
     */
    public function purgePaths(array $paths): bool;

    /**
     * Purge every URL below the given site-relative prefixes, including
     * query-string variants such as /discover?page=2.
     *
     * @param string[] $prefixes
     */
    public function purgePrefixes(array $prefixes): bool;
}
