<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth;
use App\Contracts\RememberMeInterface;
use App\Contracts\UserInterface;
use App\Contracts\UserProviderServiceInterface;
use App\DataObjects\RegisterUserData;
use App\Entity\User;
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

        $auth = $this->auth($session, $userProvider);

        $this->assertNull($auth->user());
        $this->assertNull($session->get('user'));
    }

    public function testItKeepsTheSessionForAnExistingAccount(): void
    {
        $session = new InMemorySession();
        $session->put('user', 7);

        $userProvider = $this->createMock(UserProviderServiceInterface::class);
        $userProvider->method('getById')->with(7)->willReturn($this->createMock(UserInterface::class));

        $auth = $this->auth($session, $userProvider);

        $this->assertNotNull($auth->user());
        $this->assertSame(7, $session->get('user'));
    }

    public function testItDoesNotLookUpAUserWithoutASession(): void
    {
        $session = new InMemorySession();

        $userProvider = $this->createMock(UserProviderServiceInterface::class);
        $userProvider->expects($this->never())->method('getById');

        $auth = $this->auth($session, $userProvider);

        $this->assertNull($auth->user());
    }

    public function testItIssuesARememberCookieWhenTheUserOptsIn(): void
    {
        $session = new InMemorySession();
        $user = $this->user();

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->once())->method('issue')->with($user);
        $rememberMe->expects($this->never())->method('clear');

        $this->auth($session, $this->createMock(UserProviderServiceInterface::class), $rememberMe)
            ->login($user, true);

        $this->assertSame(7, $session->get('user'));
        $this->assertTrue($session->get('_remember'));
    }

    public function testItDoesNotIssueARememberCookieWhenTheUserOptsOut(): void
    {
        $session = new InMemorySession();
        $user = $this->user();

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->never())->method('issue');
        $rememberMe->expects($this->once())->method('clear');

        $this->auth($session, $this->createMock(UserProviderServiceInterface::class), $rememberMe)
            ->login($user, false);

        $this->assertSame(7, $session->get('user'));
        // An explicit false, so a legacy session can be told apart from this.
        $this->assertFalse($session->get('_remember'));
    }

    public function testItRestoresASessionWithoutReissuingTheCookie(): void
    {
        $session = new InMemorySession();
        $user = $this->user();

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->never())->method('issue');
        $rememberMe->expects($this->never())->method('clear');

        $this->auth($session, $this->createMock(UserProviderServiceInterface::class), $rememberMe)
            ->restoreSession($user);

        $this->assertSame(7, $session->get('user'));
        $this->assertTrue($session->get('_remember'));
    }

    public function testItForgetsTheRememberCookieOnLogout(): void
    {
        $session = new InMemorySession();
        $session->put('user', 7);
        $session->put('_remember', true);

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->once())->method('clear');

        $userProvider = $this->createMock(UserProviderServiceInterface::class);
        $userProvider->method('getById')->willReturn($this->user());

        $auth = $this->auth($session, $userProvider, $rememberMe);
        $auth->user();
        $auth->logout();

        $this->assertNull($session->get('user'));
        $this->assertNull($session->get('_remember'));
    }

    public function testItRotatesTheKeyAndKeepsThisBrowserSignedIn(): void
    {
        $session = new InMemorySession();
        $session->put('user', 7);
        $session->put('_remember', true);

        $user = $this->user();

        $userProvider = $this->createMock(UserProviderServiceInterface::class);
        $userProvider->method('getById')->willReturn($user);
        $userProvider->expects($this->once())->method('rotateRememberKey')->with($user)->willReturn('new-key');

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->once())->method('issue')->with($user);

        $this->auth($session, $userProvider, $rememberMe)->logoutEverywhere();

        $this->assertSame(7, $session->get('user'));
    }

    public function testItDoesNotReissueForABrowserThatWasNotRemembered(): void
    {
        $session = new InMemorySession();
        $session->put('user', 7);
        $session->put('_remember', false);

        $user = $this->user();

        $userProvider = $this->createMock(UserProviderServiceInterface::class);
        $userProvider->method('getById')->willReturn($user);
        $userProvider->expects($this->once())->method('rotateRememberKey')->willReturn('new-key');

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->never())->method('issue');

        $this->auth($session, $userProvider, $rememberMe)->logoutEverywhere();
    }

    public function testItLogsOutWhenThereIsNoUserToRevoke(): void
    {
        $session = new InMemorySession();

        $userProvider = $this->createMock(UserProviderServiceInterface::class);
        $userProvider->expects($this->never())->method('rotateRememberKey');

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->once())->method('clear');

        $this->auth($session, $userProvider, $rememberMe)->logoutEverywhere();

        $this->assertNull($session->get('user'));
    }

    public function testItRemembersNewlyRegisteredUsers(): void
    {
        $session = new InMemorySession();

        // The welcome mail is typed against the entity, not the interface.
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(7);

        $userProvider = $this->createMock(UserProviderServiceInterface::class);
        $userProvider->expects($this->once())->method('createUser')->willReturn($user);

        $rememberMe = $this->createMock(RememberMeInterface::class);
        $rememberMe->expects($this->once())->method('issue')->with($user);

        $this->auth($session, $userProvider, $rememberMe)
            ->register(new RegisterUserData(
                email: 'new@example.com',
                password: 'password123',
                verified: true
            ));

        $this->assertSame(7, $session->get('user'));
        $this->assertTrue($session->get('_remember'));
    }

    private function auth(
        InMemorySession $session,
        UserProviderServiceInterface $userProvider,
        ?RememberMeInterface $rememberMe = null
    ): Auth {
        return new Auth(
            $userProvider,
            $session,
            $rememberMe ?? $this->createMock(RememberMeInterface::class),
            $this->createMock(VerificationEmail::class),
            $this->createMock(WelcomeEmail::class)
        );
    }

    private function user(): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn(7);

        return $user;
    }
}
