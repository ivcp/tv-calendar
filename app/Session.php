<?php

declare(strict_types=1);

namespace App;

use App\Contracts\SessionInterface;
use App\DataObjects\SessionConfig;
use App\Exception\SessionException;

class Session implements SessionInterface
{
    public function __construct(private readonly SessionConfig $sessionConfig)
    {
    }

    public function start(): void
    {
        if ($this->isActive()) {
            throw new SessionException('Session has already been started');
        }

        if (headers_sent($fileName, $line)) {
            throw new SessionException('Headers have already been sent ' . $fileName . ':' . $line);
        }

        session_set_cookie_params([
            'secure' => $this->sessionConfig->secure,
            'httponly' => $this->sessionConfig->httpOnly,
            'samesite' => $this->sessionConfig->sameSite->value,
        ]);

        if (! empty($this->sessionConfig->name)) {
            session_name($this->sessionConfig->name);
        }

        if (! session_start()) {
            throw new SessionException('Unable to start session.');
        }

    }

    public function save(): void
    {
        session_write_close();
    }

    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): bool
    {
        return session_regenerate_id();
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    public function flash(string $key, array $messages): void
    {
        $_SESSION[$this->sessionConfig->flashName][$key] = $messages;

    }
    public function getFlashed(string $key): array
    {
        $messages = $_SESSION[$this->sessionConfig->flashName][$key] ?? [];

        unset($_SESSION[$this->sessionConfig->flashName][$key]);

        return $messages;
    }

}
