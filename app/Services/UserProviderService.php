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
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly NtfyService $ntfyService
    ) {}

    public function getById(int $userId): ?UserInterface
    {
        return $this->entityManager->find(User::class, $userId);
    }

    public function getByCredentials(array $credentials): ?UserInterface
    {
        $email = $this->normalizeEmail($credentials['email'] ?? null);

        if ($email === '') {
            return null;
        }

        return $this->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('LOWER(u.email) = :email')
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function emailExists(string $email): bool
    {
        $email = $this->normalizeEmail($email);

        if ($email === '') {
            return false;
        }

        return (bool) $this->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('LOWER(u.email) = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getByNtfyTopic(string $topic): ?UserInterface
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['ntfyTopic' => $topic]);
    }

    public function createUser(RegisterUserData $data): UserInterface
    {
        $user = new User();
        $user->setEmail($this->normalizeEmail($data->email));
        $user->setStartOfWeekSunday($data->startOfWeekSunday);
        $user->setNtfyTopic($data->ntfyTopic);
        $user->setNotificationTime($data->notificationTime);
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
        $user->setUpdatedAt(new DateTime());
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function updatePassword(UserInterface $user, string $password): void
    {
        $user->setPassword($this->hashPassword($password));
        $user->setUpdatedAt(new DateTime());
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function deleteUser(UserInterface $user): void
    {
        $ntfyTopic = $user->getNtfyTopic();
        $email = $user->getEmail();
        if ($ntfyTopic) {
            $this->ntfyService->deleteUser($email);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    private function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }

    private function normalizeEmail(mixed $email): string
    {
        return is_string($email) ? mb_strtolower(trim($email), 'UTF-8') : '';
    }
}
