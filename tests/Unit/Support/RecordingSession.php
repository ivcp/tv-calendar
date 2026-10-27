<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

/**
 * Session stub that actually keeps flashed data, so middleware tests can assert
 * what would be shown to the user on the next request.
 */
class RecordingSession extends InMemorySession
{
    private array $flashed = [];

    public function flash(string $key, array $messages): void
    {
        $this->flashed[$key] = $messages;
    }

    public function getFlashed(string $key): array
    {
        return $this->flashed[$key] ?? [];
    }
}
