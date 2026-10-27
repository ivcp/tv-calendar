<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use App\Exception\RateLimitException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RateLimitExceptionTest extends TestCase
{
    #[DataProvider('retryAfterProvider')]
    public function testItDescribesHowLongTheClientShouldWait(int $retryAfter, string $expectedMessage): void
    {
        $exception = new RateLimitException('email', $retryAfter);

        $this->assertSame(429, $exception->getCode());
        $this->assertSame($retryAfter, $exception->retryAfter);
        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame([$expectedMessage], $exception->errors['email']);
    }

    public static function retryAfterProvider(): array
    {
        return [
            'one second' => [1, 'Too many requests. Please try again in 1 second.'],
            'seconds' => [15, 'Too many requests. Please try again in 15 seconds.'],
            'last second before a minute' => [59, 'Too many requests. Please try again in 59 seconds.'],
            'exactly one minute' => [60, 'Too many requests. Please try again in 1 minute.'],
            'rounded up to minutes' => [61, 'Too many requests. Please try again in 2 minutes.'],
            'quarter of an hour' => [900, 'Too many requests. Please try again in 15 minutes.'],
            'one hour' => [3600, 'Too many requests. Please try again in 60 minutes.'],
        ];
    }

    public function testItTreatsAZeroRetryAfterAsOneSecond(): void
    {
        $exception = new RateLimitException('password', 0);

        $this->assertSame('Too many requests. Please try again in 1 second.', $exception->getMessage());
    }
}
