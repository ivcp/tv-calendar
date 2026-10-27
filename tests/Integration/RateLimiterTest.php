<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\RateLimiter;
use DI\Container;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private const BUCKET = 'test:integration:';

    private Container $container;
    private RateLimiter $rateLimiter;
    private Connection $connection;

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            $this->fail('running migrations failed' . PHP_EOL);
        }

        require_once __DIR__ . '/../../configs/path_constants.php';
        $this->container = require CONFIG_PATH . '/container/container.php';
        $this->rateLimiter = $this->container->get(RateLimiter::class);
        $this->connection = $this->container->get(EntityManager::class)->getConnection();
        $this->connection->executeStatement('DELETE FROM rate_limits');
    }

    public function testItAllowsRequestsUpToTheLimitAndBlocksTheNextOne(): void
    {
        $limit = 3;

        for ($attempt = 1; $attempt <= $limit; $attempt++) {
            $result = $this->rateLimiter->consume(self::BUCKET . 'login', $limit, 60);

            $this->assertTrue($result->allowed, sprintf('attempt %d should be allowed', $attempt));
            $this->assertSame($attempt, $result->hits);
            $this->assertGreaterThanOrEqual(1, $result->retryAfter);
            $this->assertLessThanOrEqual(60, $result->retryAfter);
        }

        $blocked = $this->rateLimiter->consume(self::BUCKET . 'login', $limit, 60);

        $this->assertFalse($blocked->allowed);
        $this->assertSame($limit + 1, $blocked->hits);
    }

    public function testItKeepsSeparateBucketsIndependent(): void
    {
        $this->assertTrue($this->rateLimiter->consume(self::BUCKET . 'a', 1, 60)->allowed);
        $this->assertFalse($this->rateLimiter->consume(self::BUCKET . 'a', 1, 60)->allowed);
        $this->assertTrue($this->rateLimiter->consume(self::BUCKET . 'b', 1, 60)->allowed);
    }

    public function testItRestartsTheCounterWhenTheWindowHasExpired(): void
    {
        $this->assertTrue($this->rateLimiter->consume(self::BUCKET . 'reset', 1, 60)->allowed);
        $this->assertFalse($this->rateLimiter->consume(self::BUCKET . 'reset', 1, 60)->allowed);

        $this->expire(self::BUCKET . 'reset');

        $result = $this->rateLimiter->consume(self::BUCKET . 'reset', 1, 60);

        $this->assertTrue($result->allowed);
        $this->assertSame(1, $result->hits);
    }

    public function testItPrunesOnlyExpiredBuckets(): void
    {
        $this->rateLimiter->consume(self::BUCKET . 'live', 10, 60);
        $this->rateLimiter->consume(self::BUCKET . 'expired', 10, 60);
        $this->expire(self::BUCKET . 'expired');

        $this->assertSame(1, $this->rateLimiter->prune());

        $remaining = $this->connection->fetchFirstColumn(
            'SELECT bucket FROM rate_limits WHERE bucket LIKE ?',
            [self::BUCKET . '%']
        );

        $this->assertSame([self::BUCKET . 'live'], $remaining);
    }

    public function testPruneIsSafeWhenThereIsNothingToDelete(): void
    {
        $this->rateLimiter->consume(self::BUCKET . 'live', 10, 60);

        $this->assertSame(0, $this->rateLimiter->prune());
    }

    public function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM rate_limits');
    }

    private function expire(string $bucket): void
    {
        $this->connection->executeStatement(
            "UPDATE rate_limits SET expires_at = (now() AT TIME ZONE 'UTC') - interval '1 minute' WHERE bucket = ?",
            [$bucket]
        );
    }
}
