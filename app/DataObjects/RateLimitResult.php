<?php

declare(strict_types=1);

namespace App\DataObjects;

class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $hits,
        public readonly int $retryAfter
    ) {
    }
}
