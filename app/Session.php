<?php

declare(strict_types=1);

namespace App;

use App\Contracts\SessionInterface;
use App\DataObjects\SessionConfig;
use App\Exception\SessionException;

class Session implements SessionInterface
{
    /**
     * Whether the session was started during this request. Response headers use
     * this to tell personalised responses from anonymous ones, because the
     * session is closed again before the response leaves the stack.
     */
    private bool $started = false;

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

        // Mirrors session.use_strict_mode in docker/local.ini, so the setting
        // also holds when the app runs outside the image (php -S, CI).
        // Never adopt a session id the server did not issue itself. Session
        // cookies can be replayed by a shared cache, and this makes such a
        // cookie useless instead of letting a visitor resume someone else's
        // session.
        ini_set('session.use_strict_mode', '1');

        session_set_cookie_params([
            'secure' => $this->sessionConfig->secure,
            'httponly' => $this->sessionConfig->httpOnly,
            'samesite' => $this->sessionConfig->sameSite->value,
            'lifetime' => $this->sessionConfig->lifetime
        ]);

        if (! empty($this->sessionConfig->name)) {
            session_name($this->sessionConfig->name);
        }

        if (! session_start()) {
            throw new SessionException('Unable to start session.');
        }

        $this->started = true;
    }

    public function save(): void
    {
        if ($this->isActive()) {
            session_write_close();
        }
    }

    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function wasStarted(): bool
    {
        return $this->started;
    }

    public function hasSessionCookie(): bool
    {
        $name = $this->sessionConfig->name;

        return $name !== '' && isset($_COOKIE[$name]);
    }

    public function forget(string $key): void
    {
        if (! $this->isActive()) {
            return;
        }

        unset($_SESSION[$key]);
    }

    public function regenerate(): bool
    {
        $this->ensureStarted();

        return session_regenerate_id();
    }

    public function put(string $key, mixed $value): void
    {
        $this->ensureStarted();

        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->isActive()) {
            return $default;
        }

        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    public function flash(string $key, array $messages): void
    {
        $this->ensureStarted();

        $_SESSION[$this->sessionConfig->flashName][$key] = $messages;
    }

    public function getFlashed(string $key): array
    {
        if (! $this->isActive()) {
            return [];
        }

        $messages = $_SESSION[$this->sessionConfig->flashName][$key] ?? [];

        unset($_SESSION[$this->sessionConfig->flashName][$key]);

        return $messages;
    }

    /**
     * Sessions are started on first write so anonymous GET requests stay free
     * of session cookies and can be cached for every visitor.
     */
    private function ensureStarted(): void
    {
        if (! $this->isActive()) {
            $this->start();
        }
    }
}