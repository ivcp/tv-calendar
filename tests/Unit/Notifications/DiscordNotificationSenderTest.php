<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Config;
use App\DataObjects\NotificationMessage;
use App\Exception\NotificationFailedException;
use App\Notifications\DiscordNotificationSender;
use App\Services\UrlProtectionService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class DiscordNotificationSenderTest extends TestCase
{
    private const VALID_URL = 'https://discord.com/api/webhooks/123456789012345678/'
        . 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private array $history = [];

    private function makeSender(array $responses): DiscordNotificationSender
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $config = new Config(['url_secret_key' => 'secret']);

        return new DiscordNotificationSender(new Client(['handler' => $stack]), $config);
    }

    private function encryptedAddress(string $url): string
    {
        return (new UrlProtectionService('secret'))->encrypt($url);
    }

    private function message(string $url): NotificationMessage
    {
        return new NotificationMessage($this->encryptedAddress($url), 'title', 'message', 'link');
    }

    public function testSendsToAValidDiscordWebhookWithoutFollowingRedirects(): void
    {
        $sender = $this->makeSender([new Response(204)]);

        $sender->send($this->message(self::VALID_URL));

        $this->assertCount(1, $this->history);
        $this->assertSame(self::VALID_URL, (string) $this->history[0]['request']->getUri());
        $this->assertFalse($this->history[0]['options']['allow_redirects']);
    }

    public function testRefusesToSendToAnUntrustedUrl(): void
    {
        $sender = $this->makeSender([]);

        try {
            $sender->send($this->message('http://127.0.0.1:8080/internal'));
            $this->fail('Expected NotificationFailedException');
        } catch (NotificationFailedException) {
            // expected
        }

        $this->assertCount(0, $this->history);
    }

    public function testRefusesToSendWhenTheStoredAddressIsNotDecryptable(): void
    {
        $sender = $this->makeSender([]);

        $message = new NotificationMessage(base64_encode(random_bytes(32)), 'title', 'message', 'link');

        // openssl_decrypt() emits warnings for garbage input; silence them so
        // the test only asserts the sender's behaviour.
        set_error_handler(static fn (): bool => true);

        try {
            $this->expectException(NotificationFailedException::class);
            $sender->send($message);
        } finally {
            restore_error_handler();
            $this->assertCount(0, $this->history);
        }
    }
}
