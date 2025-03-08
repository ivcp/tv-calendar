<?php

declare(strict_types=1);

namespace App;

use App\Contracts\AuthInterface;
use App\Contracts\SessionInterface;
use App\Contracts\UserInterface;
use App\Contracts\UserProviderServiceInterface;
use App\DataObjects\RegisterUserData;
use App\Mail\VerificatonEmail;

class Auth implements AuthInterface
{
    private ?UserInterface $user = null;

    public function __construct(
        private readonly UserProviderServiceInterface $userProvider,
        private readonly SessionInterface $session,
        private readonly VerificatonEmail $verificatonEmail,
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
            return null;
        }

        $this->user = $user;

        return $this->user;
    }

    public function attemptLogin(array $credentials): ?UserInterface
    {
        $user = $this->userProvider->getByCredentials($credentials);
        if (!$user || ! $this->checkCredentials($user, $credentials)) {
            return null;
        }

        $this->login($user);
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
        $this->session->regenerate();

        $this->user = null;
    }

    public function register(RegisterUserData $data): UserInterface
    {
        $user = $this->userProvider->createUser($data);

        $this->login($user);
        if (! $data->verified) {
            $this->verificatonEmail->send($user);
        }
        return $user;
    }

    public function login(UserInterface $user): void
    {
        $this->session->regenerate();
        $this->session->put('user', $user->getId());
        $this->user = $user;
    }
}
