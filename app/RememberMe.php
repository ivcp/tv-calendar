<?php

declare(strict_types=1);

namespace App;

use App\Contracts\RememberMeInterface;
use App\Contracts\UserInterface;
use App\Contracts\UserProviderServiceInterface;
use App\Enum\SameSite;


class RememberMe implements RememberMeInterface
{
    private const VERSION = 'v1';

    public function __construct(
        private readonly Config $config,
        private readonly UserProviderServiceInterface $users
    ) {}

    public function isEnabled(): bool
    {
        return $this->appKey() !== '';
    }

    public function hasCookie(): bool
    {
        return is_string($_COOKIE[$this->cookieName()] ?? null);
    }

    public function issue(UserInterface $user): void
    {
        if (! $this->isEnabled()) {
            error_log('Remember me is disabled: APP_KEY is not configured.');

            return;
        }

        $this->users->ensureRememberKey($user);

        $expiresAt = time() + (int) $this->config->get('remember.lifetime', 0);
        $token     = $this->buildToken($user, $expiresAt);

        // The browser keeps the cookie for a grace period after the signed
        // expiry. The token is already invalid by then; the extra time only
        // lets the middleware recognise "expired remembered user" and send
        // them to the login page instead of treating them as a guest.
        $cookieExpiresAt = $expiresAt + (int) $this->config->get('remember.expiry_grace', 0);

        $this->setCookie($token, $cookieExpiresAt);

        // Let the rest of this request see the cookie it just issued.
        $_COOKIE[$this->cookieName()] = $token;
    }

    public function clear(): void
    {
        $this->setCookie('', time() - 3600);

        unset($_COOKIE[$this->cookieName()]);
    }

    public function verify(bool $clearOnFailure = true): ?UserInterface
    {
        if (! $this->hasCookie()) {
            return null;
        }

        $user = $this->verifyToken((string) $_COOKIE[$this->cookieName()]);

        if (! $user && $clearOnFailure) {
            // Expired, tampered, or revoked: do not keep sending it.
            $this->clear();
        }

        return $user;
    }

    public function verifyToken(string $value): ?UserInterface
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $raw = $this->base64UrlDecode($value);
        if ($raw === null) {
            return null;
        }

        $parts = explode('|', $raw);
        if (count($parts) !== 4) {
            return null;
        }

        [$version, $userId, $expiresAt, $signature] = $parts;

        if ($version !== self::VERSION || ! ctype_digit($userId) || ! ctype_digit($expiresAt)) {
            return null;
        }

        if ((int) $expiresAt <= time()) {
            return null;
        }

        $user = $this->users->getById((int) $userId);
        if (! $user || (string) $user->getRememberKey() === '') {
            return null;
        }

        return hash_equals($this->sign($user, (int) $expiresAt), $signature) ? $user : null;
    }

    public function buildToken(UserInterface $user, int $expiresAt): string
    {
        $payload = $this->payload($user, $expiresAt);

        return $this->base64UrlEncode($payload . '|' . $this->sign($user, $expiresAt));
    }

    private function sign(UserInterface $user, int $expiresAt): string
    {
        $material = $this->payload($user, $expiresAt)
            . '|' . $user->getRememberKey()
            . '|' . ($user->getPassword() ?? '');

        return hash_hmac('sha256', $material, $this->appKey());
    }

    private function payload(UserInterface $user, int $expiresAt): string
    {
        return self::VERSION . '|' . $user->getId() . '|' . $expiresAt;
    }

    private function setCookie(string $value, int $expires): void
    {
        setcookie($this->cookieName(), $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => (bool) $this->config->get('remember.secure', true),
            'httponly' => (bool) $this->config->get('remember.httponly', true),
            'samesite' => SameSite::from((string) $this->config->get('remember.samesite', 'lax'))->value,
        ]);
    }

    private function cookieName(): string
    {
        return (string) $this->config->get('remember.cookie_name', '');
    }

    private function appKey(): string
    {
        return (string) $this->config->get('app_key', '');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
