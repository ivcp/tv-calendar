<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Show;
use App\Entity\User;
use App\Entity\UserShows;
use App\Exception\BadRequestException;
use App\Exception\ShowNotInListException;
use Doctrine\ORM\EntityManager;
use Exception;

class UserShowsService
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function add(Show $show, User $user): void
    {
        $userShow = new UserShows();
        $userShow->setUser($user);
        $userShow->setShow($show);

        $this->entityManager->persist($userShow);
        $this->entityManager->flush();

    }

    public function delete(Show $show, User $user): void
    {
        $userShow = $this->entityManager->getRepository(UserShows::class)->findOneBy(['user' => $user, 'show' => $show]);
        if (! $userShow) {
            throw new ShowNotInListException();
        }

        $this->entityManager->remove($userShow);
        $this->entityManager->flush();
    }

    public function get(User $user): array
    {
        return  $this->entityManager->getRepository(UserShows::class)->findBy(['user' => $user]);
    }
}
