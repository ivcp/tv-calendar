<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Config;
use App\Entity\User;
use App\Mail\VerificationLinkService;
use DateTime;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class VerificationLinkServiceTest extends TestCase
{
    private const APP_URL = 'https://tv-calendar.test';
    private const APP_KEY = 'test-app-key';
    private const EMAIL   = 'viewer@example.com';
    private const EXPIRES = 1700000000;

    private function makeService(string $appUrl = self::APP_URL): VerificationLinkService
    {
        return new VerificationLinkService(new Config([
            'app_url' => $appUrl,
            'app_key' => self::APP_KEY,
        ]));
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail(self::EMAIL);

        $reflection = new ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, 42);

        return $user;
    }

    private function expectedUrl(int $userId, string $email, int $expiration): string
    {
        $unsigned = sprintf(
            '%s/verify/%d/%s?expiration=%d',
            self::APP_URL,
            $userId,
            sha1($email),
            $expiration
        );

        return $unsigned . '&signature=' . hash_hmac('sha256', $unsigned, self::APP_KEY);
    }

    public function testItGeneratesTheSameUrlAsTheOriginalImplementation(): void
    {
        $link = $this->makeService()->forUser($this->makeUser(), new DateTime('@' . self::EXPIRES));

        $this->assertSame($this->expectedUrl(42, self::EMAIL, self::EXPIRES), $link);
    }

    public function testItsSignatureValidatesTheWayTheMiddlewareChecksIt(): void
    {
        $link = $this->makeService()->generate(7, 'other@example.com', new DateTime('@' . self::EXPIRES));

        $parts = parse_url($link);
        $this->assertIsArray($parts);

        parse_str($parts['query'], $query);
        $originalSignature = $query['signature'];
        unset($query['signature']);

        $rebuilt = $parts['scheme'] . '://' . $parts['host'] . $parts['path'] . '?' . http_build_query($query);
        $signature = hash_hmac('sha256', $rebuilt, self::APP_KEY);

        $this->assertTrue(hash_equals($signature, $originalSignature));
    }

    public function testItNormalizesATrailingSlashInTheAppUrl(): void
    {
        $link = $this->makeService(self::APP_URL . '/')->forUser($this->makeUser(), new DateTime('@' . self::EXPIRES));

        $this->assertStringNotContainsString('//verify', $link);
        $this->assertSame($this->expectedUrl(42, self::EMAIL, self::EXPIRES), $link);
    }

    public function testTheSignatureChangesWithTheEmailAndTheExpiration(): void
    {
        $service = $this->makeService();

        $base = $service->generate(42, self::EMAIL, new DateTime('@' . self::EXPIRES));
        $otherEmail = $service->generate(42, 'someone@example.com', new DateTime('@' . self::EXPIRES));
        $otherExpiration = $service->generate(42, self::EMAIL, new DateTime('@' . (self::EXPIRES + 1)));
        $otherUser = $service->generate(43, self::EMAIL, new DateTime('@' . self::EXPIRES));

        $this->assertNotSame($base, $otherEmail);
        $this->assertNotSame($base, $otherExpiration);
        $this->assertNotSame($base, $otherUser);
    }
}
