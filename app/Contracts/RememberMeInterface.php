<?php

declare(strict_types=1);

namespace App\Contracts;

interface RememberMeInterface
{
    /**
     * Whether the signing key is configured; the feature is inert without it.
     */
    public function isEnabled(): bool;

    /**
     * Whether the request carries a remember-me cookie at all.
     */
    public function hasCookie(): bool;

    /**
     * Issues a fresh cookie for the user. Requires enabled and a remember key.
     */
    public function issue(UserInterface $user): void;

    /**
     * Expires the cookie in the browser and forgets it for this request.
     */
    public function clear(): void;

    /**
     * Returns the user behind the request cookie, or null. Clears invalid
     * cookies unless told not to, so callers can act on a lapsed login first.
     */
    public function verify(bool $clearOnFailure = true): ?UserInterface;

    /**
     * Pure verification of a cookie value: no cookie access, no side effects.
     */
    public function verifyToken(string $value): ?UserInterface;

    /**
     * Pure token construction, exposed for testing.
     */
    public function buildToken(UserInterface $user, int $expiresAt): string;
}
