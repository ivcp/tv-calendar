<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\UserInterface;
use App\Contracts\UserProviderServiceInterface;
use App\DataObjects\RegisterUserData;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManager;

class UserProviderService implements UserProviderServiceInterface
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function getById(int $userId): ?UserInterface
    {
        return $this->entityManager->find(User::class, $userId);
    }

    public function getByCredentials(array $credentials): ?UserInterface
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $credentials['email']]);
    }

    public function createUser(RegisterUserData $data): UserInterface
    {
        $user = new User();
        $user->setEmail($data->email);
        if ($data->password) {
            $user->setPassword($this->hashPassword($data->password));
        }
        if ($data->verified) {
            $user->setVerifiedAt(new DateTime());
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function verifyUser(UserInterface $user): void
    {
        $user->setVerifiedAt(new DateTime());
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
    public function updatePassword(UserInterface $user, string $password): void
    {
        $user->setPassword($this->hashPassword($password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }
}
