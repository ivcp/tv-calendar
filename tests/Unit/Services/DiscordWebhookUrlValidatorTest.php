<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DiscordWebhookUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DiscordWebhookUrlValidatorTest extends TestCase
{
    private const VALID_URL = 'https://discord.com/api/webhooks/123456789012345678/'
        . 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    #[DataProvider('validUrls')]
    public function testAcceptsDiscordWebhookUrls(string $url): void
    {
        $this->assertTrue(DiscordWebhookUrlValidator::isValid($url));
    }

    public static function validUrls(): array
    {
        return [
            'discord.com' => [self::VALID_URL],
            'discordapp.com' => [str_replace('discord.com', 'discordapp.com', self::VALID_URL)],
            'canary subdomain' => [str_replace('discord.com', 'canary.discord.com', self::VALID_URL)],
            'explicit https port' => [str_replace('discord.com', 'discord.com:443', self::VALID_URL)],
            'underscore in token' => [
                str_replace('aaaaaaaaaa', 'aa_aaa-aaa', self::VALID_URL),
            ],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function testRejectsEverythingElse(?string $url): void
    {
        $this->assertFalse(DiscordWebhookUrlValidator::isValid($url));
    }

    public static function invalidUrls(): array
    {
        $valid = self::VALID_URL;

        return [
            'null' => [null],
            'empty' => [''],
            'plain http' => [str_replace('https://', 'http://', $valid)],
            'no scheme' => [str_replace('https://', '', $valid)],
            'other host' => [str_replace('discord.com', 'evil.com', $valid)],
            'lookalike host' => [str_replace('discord.com', 'discord.com.evil.com', $valid)],
            'host suffix' => [str_replace('discord.com', 'evildiscord.com', $valid)],
            'localhost' => [str_replace('discord.com', 'localhost', $valid)],
            'private ip' => [str_replace('discord.com', '10.0.0.1', $valid)],
            'loopback ip' => [str_replace('discord.com', '127.0.0.1', $valid)],
            'link local ip' => [str_replace('discord.com', '169.254.169.254', $valid)],
            'ipv6 loopback' => [str_replace('discord.com', '[::1]', $valid)],
            'ipv6 mapped loopback' => [str_replace('discord.com', '[::ffff:127.0.0.1]', $valid)],
            'userinfo' => [str_replace('discord.com', 'user:pass@discord.com', $valid)],
            'userinfo with other host' => [
                'https://discord.com@127.0.0.1/api/webhooks/123456789012345678/'
                . 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ],
            'non standard port' => [str_replace('discord.com', 'discord.com:8080', $valid)],
            'query string' => [$valid . '?foo=bar'],
            'fragment' => [$valid . '#foo'],
            'wrong path' => [str_replace('/api/webhooks/', '/api/v1/', $valid)],
            'missing token' => [str_replace('/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '', $valid)],
            'short token' => [str_replace('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'aaa', $valid)],
            'non numeric id' => [str_replace('123456789012345678', 'abc', $valid)],
            'trailing slash' => [$valid . '/'],
            'path traversal' => [
                'https://discord.com/api/webhooks/123456789012345678/../../../admin',
            ],
            'backslash host confusion' => [
                'https://discord.com\\@127.0.0.1/api/webhooks/123456789012345678/'
                . 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ],
            'whitespace' => [
                'https://discord.com/api/webhooks/123456789012345678/' . "\n" . 'aaaaaaaaaa',
            ],
            'too long' => [
                'https://discord.com/api/webhooks/123456789012345678/' . str_repeat('a', 500),
            ],
        ];
    }
}
