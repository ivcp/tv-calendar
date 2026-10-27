<?php

declare(strict_types=1);

namespace App\Exception;

class RateLimitException extends ValidationException
{
    public function __construct(string $field, public readonly int $retryAfter)
    {
        $message = self::describeRetryAfter($retryAfter);

        parent::__construct([$field => [$message]], $message, 429);
    }

    private static function describeRetryAfter(int $retryAfter): string
    {
        $seconds = max(1, $retryAfter);

        if ($seconds < 60) {
            return sprintf(
                'Too many requests. Please try again in %d %s.',
                $seconds,
                $seconds === 1 ? 'second' : 'seconds'
            );
        }

        $minutes = (int) ceil($seconds / 60);

        return sprintf(
            'Too many requests. Please try again in %d %s.',
            $minutes,
            $minutes === 1 ? 'minute' : 'minutes'
        );
    }
}
