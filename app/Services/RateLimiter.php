<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\RateLimitResult;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use RuntimeException;

class RateLimiter
{
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly EntityManager $entityManager
    ) {
    }

    /**
     * Increments the fixed-window counter for the given bucket and reports whether
     * the request is within the limit.
     *
     * The counter is updated with a single atomic upsert, so parallel requests
     * cannot read-then-write past the limit. A bucket that has expired restarts
     * from 1 instead of accumulating across windows.
     */
    public function consume(string $bucket, int $limit, int $window): RateLimitResult
    {
        $now       = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt = $now->modify(sprintf('+%d seconds', max(1, $window)));

        $row = $this->entityManager->getConnection()->executeQuery(
            "INSERT INTO rate_limits (bucket, hits, expires_at)
             VALUES (:bucket, 1, :expiresAt)
             ON CONFLICT (bucket) DO UPDATE SET
                hits = CASE
                    WHEN rate_limits.expires_at <= (now() AT TIME ZONE 'UTC') THEN 1
                    ELSE rate_limits.hits + 1
                END,
                expires_at = CASE
                    WHEN rate_limits.expires_at <= (now() AT TIME ZONE 'UTC') THEN excluded.expires_at
                    ELSE rate_limits.expires_at
                END
             RETURNING hits, expires_at",
            [
                'bucket'    => $bucket,
                'expiresAt' => $expiresAt->format(self::TIMESTAMP_FORMAT),
            ]
        )->fetchAssociative();

        if ($row === false) {
            throw new RuntimeException('Unable to update the rate limit bucket.');
        }

        $hits            = (int) $row['hits'];
        $bucketExpiresAt = new DateTimeImmutable((string) $row['expires_at'], new DateTimeZone('UTC'));

        return new RateLimitResult(
            allowed: $hits <= max(1, $limit),
            hits: $hits,
            retryAfter: max(1, $bucketExpiresAt->getTimestamp() - $now->getTimestamp())
        );
    }

    /**
     * Removes counters whose window has already ended.
     */
    public function prune(): int
    {
        return (int) $this->entityManager->getConnection()->executeStatement(
            "DELETE FROM rate_limits WHERE expires_at < (now() AT TIME ZONE 'UTC')"
        );
    }
}
