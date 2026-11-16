<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataObjects\RegisterUserData;

interface AuthInterface
{
    public function user(): ?UserInterface;
    public function attemptLogin(array $data, bool $remember = false): ?UserInterface;
    public function checkCredentials(UserInterface $user, array $credentials): bool;
    public function logout(): void;
    public function logoutEverywhere(): void;
    public function register(RegisterUserData $data): UserInterface;
    public function login(UserInterface $user, bool $remember = false): void;
    public function restoreSession(UserInterface $user): void;
}
