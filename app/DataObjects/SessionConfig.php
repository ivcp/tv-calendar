<?php

declare(strict_types=1);

namespace App\DataObjects;

use App\Enum\SameSite;

class SessionConfig
{
    public function __construct(
        public readonly string $name,
        public readonly bool $secure,
        public readonly bool $httpOnly,
        public readonly string $flashName,
        public readonly SameSite $sameSite,
        public readonly int $lifetime,
    ) {
    }
}
