<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Contracts\UserInterface;
use App\Contracts\UserProviderServiceInterface;
use App\RememberMe;
use PHPUnit\Framework\TestCase;

final class RememberMeTest extends TestCase
{
    private const COOKIE = 'test_remember';
    private const KEY    = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const PASSWORD = '$2y$12$abcdefghijklmnopqrstuv';

    /** @var array<string, mixed> */
    private array $cookies;

    protected function setUp(): void
    {
        $this->cookies = $_COOKIE;
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->cookies;
    }

    public function testItVerifiesAValidToken(): void
    {
        $user = $this->user();
        $remember = $this->rememberMe($this->providerReturning($user));

        $token = $remember->buildToken($user, time() + 3600);

        $this->assertSame($user, $remember->verifyToken($token));
    }

    public function testItRejectsAnExpiredTokenWithoutLoadingTheUser(): void
    {
        $user = $this->user();
        $provider = $this->createMock(UserProviderServiceInterface::class);
        $provider->expects($this->never())->method('getById');

        $remember = $this->rememberMe($provider);
        $token = $remember->buildToken($user, time() - 1);

        $this->assertNull($remember->verifyToken($token));
    }

    public function testItRejectsATamperedExpiry(): void
    {
        $user = $this->user();
        $remember = $this->rememberMe($this->providerReturning($user));

        $parts = explode('|', $this->decode($remember->buildToken($user, time() + 3600)));
        [$version, $id, $expires, $signature] = $parts;

        $tampered = $this->encode($version . '|' . $id . '|' . ((int) $expires + 999) . '|' . $signature);

        $this->assertNull($remember->verifyToken($tampered));
    }

    public function testItRejectsATamperedUserId(): void
    {
        $user = $this->user();
        $remember = $this->rememberMe($this->providerReturning($user));

        $parts = explode('|', $this->decode($remember->buildToken($user, time() + 3600)));
        [$version, , $expires, $signature] = $parts;

        // The server loads the user named in the payload; id 43 cannot be
        // covered by a signature that was computed over id 42.
        $tampered = $this->encode($version . '|43|' . $expires . '|' . $signature);
        $provider = $this->createMock(UserProviderServiceInterface::class);
        $provider->method('getById')->willReturn($this->user(id: 43));

        $this->assertNull($this->rememberMe($provider)->verifyToken($tampered));
    }

    public function testItRejectsATokenSignedWithAnotherKey(): void
    {
        $user = $this->user();

        $foreign = $this->rememberMe(
            $this->providerReturning($user),
            $this->config('another-app-key')
        );

        $token = $foreign->buildToken($user, time() + 3600);

        $this->assertNull($this->rememberMe($this->providerReturning($user))->verifyToken($token));
    }

    public function testItRejectsATokenAfterThePasswordChanged(): void
    {
        $user = $this->user();
        $rotated = $this->user(password: '$2y$12$zzzzzzzzzzzzzzzzzzzzzz');

        $remember = $this->rememberMe($this->providerReturning($rotated));
        $token = $remember->buildToken($user, time() + 3600);

        $this->assertNull($remember->verifyToken($token));
    }

    public function testItRejectsATokenAfterTheRememberKeyWasRotated(): void
    {
        $user = $this->user();
        $rotated = $this->user(key: str_repeat('b', 64));

        $remember = $this->rememberMe($this->providerReturning($rotated));
        $token = $remember->buildToken($user, time() + 3600);

        $this->assertNull($remember->verifyToken($token));
    }

    public function testItRejectsMalformedTokens(): void
    {
        $remember = $this->rememberMe($this->providerReturning($this->user()));

        $this->assertNull($remember->verifyToken(''));
        $this->assertNull($remember->verifyToken('not base64!'));
        $this->assertNull($remember->verifyToken($this->encode('v1|42')));
        $this->assertNull($remember->verifyToken($this->encode('v2|42|' . (time() + 60) . '|deadbeef')));
        $this->assertNull($remember->verifyToken($this->encode('v1|abc|' . (time() + 60) . '|deadbeef')));
    }

    public function testItRejectsATokenForADeletedAccount(): void
    {
        $user = $this->user();
        $provider = $this->createMock(UserProviderServiceInterface::class);
        $provider->method('getById')->willReturn(null);

        $remember = $this->rememberMe($provider);
        $token = $remember->buildToken($user, time() + 3600);

        $this->assertNull($remember->verifyToken($token));
    }

    public function testItTreatsAnArrayCookieAsAbsent(): void
    {
        $_COOKIE[self::COOKIE] = ['injected'];

        $remember = $this->rememberMe($this->providerReturning($this->user()));

        $this->assertFalse($remember->hasCookie());
        $this->assertNull($remember->verify());
    }

    public function testItIssuesAndClearsTheCookie(): void
    {
        $user = $this->user();
        $provider = $this->providerReturning($user);
        $provider->expects($this->once())->method('ensureRememberKey')->with($user)->willReturn(self::KEY);

        $remember = $this->rememberMe($provider);
        $remember->issue($user);

        $this->assertTrue($remember->hasCookie());
        $this->assertSame($user, $remember->verifyToken($_COOKIE[self::COOKIE]));

        $remember->clear();

        $this->assertFalse($remember->hasCookie());
    }

    public function testItLeavesAnInvalidCookieForTheCallerToHandle(): void
    {
        $_COOKIE[self::COOKIE] = 'garbage';

        $remember = $this->rememberMe($this->providerReturning($this->user()));

        $this->assertNull($remember->verify(false));
        // The middleware needs the cookie to stay put until a page navigation
        // can explain the expired login; only that path clears it.
        $this->assertTrue($remember->hasCookie());
    }

    public function testItIsInertWithoutAnAppKey(): void
    {
        $user = $this->user();
        $provider = $this->createMock(UserProviderServiceInterface::class);
        $provider->expects($this->never())->method('ensureRememberKey');
        $provider->expects($this->never())->method('getById');

        $remember = $this->rememberMe($provider, $this->config(''));

        $this->assertFalse($remember->isEnabled());

        $remember->issue($user);

        $this->assertFalse($remember->hasCookie());
        $this->assertNull($remember->verifyToken('anything'));
    }

    private function rememberMe(UserProviderServiceInterface $provider, ?Config $config = null): RememberMe
    {
        return new RememberMe($config ?? $this->config(), $provider);
    }

    private function config(string $appKey = 'test-app-key'): Config
    {
        return new Config([
            'app_key' => $appKey,
            'remember' => [
                'cookie_name' => self::COOKIE,
                'lifetime' => 60 * 60 * 24 * 180,
                'secure' => false,
                'httponly' => true,
                'samesite' => 'lax',
            ],
        ]);
    }

    private function providerReturning(UserInterface $user): UserProviderServiceInterface
    {
        $provider = $this->createMock(UserProviderServiceInterface::class);
        $provider->method('getById')->willReturn($user);

        return $provider;
    }

    private function user(int $id = 42, string $key = self::KEY, ?string $password = self::PASSWORD): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn($id);
        $user->method('getRememberKey')->willReturn($key);
        $user->method('getPassword')->willReturn($password);

        return $user;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
