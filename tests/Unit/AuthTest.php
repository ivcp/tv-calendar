<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth;
use App\Contracts\UserInterface;
use App\Contracts\UserProviderServiceInterface;
use App\Mail\VerificationEmail;
use App\Mail\WelcomeEmail;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\InMemorySession;

final class AuthTest extends TestCase
{
    public function testItClearsASessionThatPointsAtADeletedAccount(): void
    {
        $session = new InMemorySession();
        $session->put('user', 7);

        $userProvider = $this->createMock(UserProviderServiceInterface::class);
        $userProvider->expects($this->once())->method('getById')->with(7)->willReturn(null);

        $auth = new Auth(
            $userProvider,
            $session,
            $this->createMock(VerificationEmail::class),
            $this->createMock(WelcomeEmail::class)
        );

        $this->assertNull($auth->user());
        $this->assertNull($session->get('user'));
    }

    public function testItKeepsTheSessionForAnExistingAccount(): void
    {
        $session = new InMemorySession();
        $session->put('user', 7);

        $userProvider = $this->createMock(UserProviderServiceInterface::class);
        $userProvider->method('getById')->with(7)->willReturn($this->createMock(UserInterface::class));

        $auth = new Auth(
            $userProvider,
            $session,
            $this->createMock(VerificationEmail::class),
            $this->createMock(WelcomeEmail::class)
        );

        $this->assertNotNull($auth->user());
        $this->assertSame(7, $session->get('user'));
    }

    public function testItDoesNotLookUpAUserWithoutASession(): void
    {
        $session = new InMemorySession();

        $userProvider = $this->createMock(UserProviderServiceInterface::class);
        $userProvider->expects($this->never())->method('getById');

        $auth = new Auth(
            $userProvider,
            $session,
            $this->createMock(VerificationEmail::class),
            $this->createMock(WelcomeEmail::class)
        );

        $this->assertNull($auth->user());
    }
}
