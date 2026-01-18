<?php

declare(strict_types=1);

namespace App\DataObjects;


class NotificationMessage
{
    public function __construct(
        public readonly string $address,
        public readonly string $title,
        public readonly string $message,
        public readonly string $showLink
    ) {}
}
