<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\UserProviderServiceInterface;
use App\Entity\PasswordReset;
use App\Entity\User;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Parameter;

class PasswordResetService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly UserProviderServiceInterface $userProviderService
    ) {
    }

    public function generate(string $email): PasswordReset
    {
        $passwordReset = new PasswordReset();

        $passwordReset->setToken(bin2hex(random_bytes(32)));
        $passwordReset->setExpiration(new DateTime('+30 minutes'));
        $passwordReset->setEmail($email);

        $this->entityManager->persist($passwordReset);
        $this->entityManager->flush();

        return $passwordReset;
    }

    public function deactivateAllPasswordResets(string $email): void
    {
        $this->entityManager
            ->getRepository(PasswordReset::class)
            ->createQueryBuilder('pr')
            ->update()
            ->set('pr.isActive', 'false')
            ->where('pr.email = :email')
            ->andWhere('pr.isActive = true')
            ->setParameter('email', $email)
            ->getQuery()
            ->execute();
    }

    public function findByToken(string $token): ?PasswordReset
    {
        return $this->entityManager
            ->getRepository(PasswordReset::class)
            ->createQueryBuilder('pr')
            ->select('pr')
            ->where('pr.token = :token')
            ->andWhere('pr.isActive = :active')
            ->andWhere('pr.expiration > :now')
            ->setParameters(
                new ArrayCollection(
                    array(
                    new Parameter('token', $token),
                    new Parameter('active', true),
                    new Parameter('now', new DateTime()),
                )
                )
            )
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function updatePassword(User $user, string $password): void
    {
        $this->entityManager->wrapInTransaction(function () use ($user, $password) {
            $this->deactivateAllPasswordResets($user->getEmail());
            $this->userProviderService->updatePassword($user, $password);
        });
    }
}
