<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Contracts\SessionInterface;

class InMemorySession implements SessionInterface
{
    private array $data = [];

    public function start(): void
    {
    }

    public function save(): void
    {
    }

    public function isActive(): bool
    {
        return true;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): bool
    {
        return true;
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function flash(string $key, array $messages): void
    {
    }

    public function getFlashed(string $key): array
    {
        return [];
    }
}
