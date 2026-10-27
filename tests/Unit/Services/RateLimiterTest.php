<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RateLimiter;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RateLimiterTest extends TestCase
{
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    public function testItAllowsARequestAtTheLimit(): void
    {
        $this->connection->method('executeQuery')->willReturn($this->resultWith(5, '+60 seconds'));

        $result = $this->limiter()->consume('bucket', 5, 60);

        $this->assertTrue($result->allowed);
        $this->assertSame(5, $result->hits);
    }

    public function testItBlocksARequestAboveTheLimit(): void
    {
        $this->connection->method('executeQuery')->willReturn($this->resultWith(6, '+60 seconds'));

        $result = $this->limiter()->consume('bucket', 5, 60);

        $this->assertFalse($result->allowed);
        $this->assertSame(6, $result->hits);
    }

    public function testItReportsTheRemainingTimeOfTheWindow(): void
    {
        $this->connection->method('executeQuery')->willReturn($this->resultWith(1, '+900 seconds'));

        $result = $this->limiter()->consume('bucket', 40, 900);

        $this->assertGreaterThanOrEqual(898, $result->retryAfter);
        $this->assertLessThanOrEqual(900, $result->retryAfter);
    }

    public function testItIncrementsTheBucketWithAnAtomicUpsert(): void
    {
        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('INSERT INTO rate_limits (bucket, hits, expires_at)'),
                    $this->stringContains('ON CONFLICT (bucket) DO UPDATE SET'),
                    $this->stringContains('RETURNING hits, expires_at')
                ),
                $this->callback(static function (array $params): bool {
                    return 'POST /login:ip:900:40:hash' === $params['bucket']
                        && 1 === preg_match(
                            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                            (string) $params['expiresAt']
                        );
                })
            )
            ->willReturn($this->resultWith(1, '+900 seconds'));

        $result = $this->limiter()->consume('POST /login:ip:900:40:hash', 40, 900);

        $this->assertTrue($result->allowed);
    }

    public function testItReportsAMissingBucketAsAnError(): void
    {
        $this->connection->method('executeQuery')->willReturn($this->resultStub(false));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to update the rate limit bucket.');

        $this->limiter()->consume('bucket', 1, 1);
    }

    public function testItPrunesExpiredBuckets(): void
    {
        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('DELETE FROM rate_limits'),
                    $this->stringContains('expires_at <')
                )
            )
            ->willReturn(7);

        $this->assertSame(7, $this->limiter()->prune());
    }

    private function limiter(): RateLimiter
    {
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($this->connection);

        return new RateLimiter($entityManager);
    }

    private function resultWith(int $hits, string $expiresAtModifier): Result
    {
        return $this->resultStub([
            'hits' => (string) $hits,
            'expires_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->modify($expiresAtModifier)
                ->format('Y-m-d H:i:s'),
        ]);
    }

    private function resultStub(array|false $row): Result
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchAssociative')->willReturn($row);

        return $result;
    }
}
