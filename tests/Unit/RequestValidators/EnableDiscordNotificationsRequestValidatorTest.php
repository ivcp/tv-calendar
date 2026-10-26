<?php

declare(strict_types=1);

namespace Tests\Unit\RequestValidators;

use App\Exception\ValidationException;
use App\RequestValidators\EnableDiscordNotificationsRequestValidator;
use PHPUnit\Framework\TestCase;

class EnableDiscordNotificationsRequestValidatorTest extends TestCase
{
    private const VALID_URL = 'https://discord.com/api/webhooks/123456789012345678/'
        . 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testAcceptsADiscordWebhookUrl(): void
    {
        $validator = new EnableDiscordNotificationsRequestValidator();

        $data = $validator->validate(['discordWebhookUrl' => self::VALID_URL]);

        $this->assertSame(self::VALID_URL, $data['discordWebhookUrl']);
    }

    public function testRejectsAWebhookUrlPointingSomewhereElse(): void
    {
        $validator = new EnableDiscordNotificationsRequestValidator();

        $this->expectException(ValidationException::class);

        $validator->validate(['discordWebhookUrl' => 'http://169.254.169.254/latest/meta-data']);
    }

    public function testRejectsAMissingWebhookUrl(): void
    {
        $validator = new EnableDiscordNotificationsRequestValidator();

        $this->expectException(ValidationException::class);

        $validator->validate([]);
    }
}
