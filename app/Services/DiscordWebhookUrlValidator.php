<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Validates user-supplied Discord webhook URLs.
 *
 */
final class DiscordWebhookUrlValidator
{
    private const ALLOWED_HOSTS = [
        'discord.com',
        'www.discord.com',
        'canary.discord.com',
        'ptb.discord.com',
        'discordapp.com',
        'www.discordapp.com',
        'canary.discordapp.com',
        'ptb.discordapp.com',
    ];

    private const WEBHOOK_PATH_PATTERN = '#^/api/webhooks/\d{5,25}/[A-Za-z0-9._-]{10,200}$#';

    private const MAX_LENGTH = 500;

    public static function isValid(?string $url): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > self::MAX_LENGTH) {
            return false;
        }

        // Whitespace, control characters and backslashes are interpreted
        // differently by URL parsers and HTTP clients (cURL treats "\" like
        // "/"), so refuse them outright.
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        if (
            $parts === false
            || ! isset($parts['scheme'], $parts['host'], $parts['path'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            return false;
        }

        if (strtolower($parts['scheme']) !== 'https') {
            return false;
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            return false;
        }

        $host = strtolower($parts['host']);

        if (self::isIpAddress($host) || ! in_array($host, self::ALLOWED_HOSTS, true)) {
            return false;
        }

        return preg_match(self::WEBHOOK_PATH_PATTERN, $parts['path']) === 1;
    }

    private static function isIpAddress(string $host): bool
    {
        // Rejects every IP literal, which also covers private, loopback,
        // link-local and reserved ranges.
        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }
}
