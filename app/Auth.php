<?php

declare(strict_types=1);

namespace App;

use App\Contracts\AuthInterface;
use App\Contracts\RememberMeInterface;
use App\Contracts\SessionInterface;
use App\Contracts\UserInterface;
use App\Contracts\UserProviderServiceInterface;
use App\DataObjects\RegisterUserData;
use App\Mail\VerificationEmail;
use App\Mail\WelcomeEmail;

class Auth implements AuthInterface
{
    private ?UserInterface $user = null;

    public function __construct(
        private readonly UserProviderServiceInterface $userProvider,
        private readonly SessionInterface $session,
        private readonly RememberMeInterface $rememberMe,
        private readonly VerificationEmail $verificationEmail,
        private readonly WelcomeEmail $welcomeEmail
    ) {
    }

    public function user(): ?UserInterface
    {
        if ($this->user) {
            return $this->user;
        }

        $userId = $this->session->get('user');
        if (!$userId) {
            return null;
        }

        $user = $this->userProvider->getById($userId);
        if (!$user) {
            // The account was deleted while the browser still had a session.
            // Clearing the id lets the browser behave as a guest from now on.
            $this->session->forget('user');

            return null;
        }

        $this->user = $user;

        return $this->user;
    }

    public function attemptLogin(array $credentials, bool $remember = false): ?UserInterface
    {
        $user = $this->userProvider->getByCredentials($credentials);
        if (!$user || ! $this->checkCredentials($user, $credentials)) {
            return null;
        }

        $this->login($user, $remember);
        return $user;
    }

    public function checkCredentials(UserInterface $user, array $credentials): bool
    {
        if (!$user->getPassword()) {
            return false;
        }
        return password_verify($credentials['password'], $user->getPassword());
    }

    public function logout(): void
    {
        $this->session->forget('user');
        $this->session->forget('_remember');
        $this->session->regenerate();
        $this->rememberMe->clear();

        $this->user = null;
    }

    /**
     * Revokes every remembered browser by rotating the signing key, then keeps
     * this one signed in when it was a remembered browser itself.
     */
    public function logoutEverywhere(): void
    {
        $user = $this->user();
        if (! $user) {
            $this->logout();

            return;
        }

        $this->userProvider->rotateRememberKey($user);

        if ($this->session->get('_remember') === true) {
            $this->rememberMe->issue($user);
        }
    }

    public function register(RegisterUserData $data): UserInterface
    {
        $user = $this->userProvider->createUser($data);

        // Registration always remembers the browser, including Google OAuth
        // accounts that never choose a password.
        $this->login($user, true);
        if (! $data->verified) {
            $this->verificationEmail->send($user);
        } else {
            $this->welcomeEmail->send($user);
        }
        return $user;
    }

    public function login(UserInterface $user, bool $remember = false): void
    {
        $this->session->regenerate();
        $this->session->put('user', $user->getId());
        // Marks an explicit choice, so legacy sessions (no marker) can be told
        // apart from an unchecked "remember me".
        $this->session->put('_remember', $remember);
        $this->user = $user;

        if ($remember) {
            $this->rememberMe->issue($user);
        } else {
            $this->rememberMe->clear();
        }
    }

    /**
     * Resumes a user from the remember-me cookie. Deliberately does not reissue
     * the cookie: its fixed expiry must not slide forward on every visit.
     */
    public function restoreSession(UserInterface $user): void
    {
        $this->session->regenerate();
        $this->session->put('user', $user->getId());
        $this->session->put('_remember', true);
        $this->user = $user;
    }
}
