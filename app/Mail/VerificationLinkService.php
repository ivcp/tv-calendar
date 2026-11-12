<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use App\Contracts\UserInterface;
use DateTime;

/**
 * Builds the signed verification URL used by the verification emails.
 *
 * The URL shape and signature must stay in sync with
 * ValidateSignatureMiddleware, which recomputes the HMAC over the URL with the
 * `signature` parameter removed.
 */
final class VerificationLinkService
{
    /**
     * Route pattern for verification links. Reused by the router so the path
     * cannot drift from the links sent by email.
     */
    public const ROUTE_PATTERN = '/verify/{id}/{hash}';

    public function __construct(private readonly Config $config)
    {
    }

    public function forUser(UserInterface $user, DateTime $expirationDate): string
    {
        return $this->generate($user->getId(), $user->getEmail(), $expirationDate);
    }

    public function generate(int $userId, string $email, DateTime $expirationDate): string
    {
        $baseUrl = rtrim((string) $this->config->get('app_url'), '/');
        $path = str_replace(
            ['{id}', '{hash}'],
            [(string) $userId, sha1($email)],
            self::ROUTE_PATTERN
        );

        $url = $baseUrl . $path . '?expiration=' . $expirationDate->getTimestamp();
        $signature = hash_hmac('sha256', $url, (string) $this->config->get('app_key'));

        return $url . '&signature=' . $signature;
    }
}
