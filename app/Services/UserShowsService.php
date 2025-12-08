<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Show;
use App\Entity\User;
use App\Entity\UserShows;
use App\Enum\Genres;
use App\Exception\ShowNotInListException;
use BackedEnum;
use Doctrine\ORM\EntityManager;

class UserShowsService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ShowService $showService
    ) {}

    public function add(Show $show, User $user): void
    {
        $userShow = new UserShows();
        $userShow->setUser($user);
        $userShow->setShow($show);

        $this->entityManager->persist($userShow);
        $this->entityManager->flush();
    }

    public function changeNotificationEnabled(Show $show, User $user, bool $notificationsEnabled): void
    {
        $userShow = $this->entityManager
            ->getRepository(UserShows::class)
            ->findOneBy(['user' => $user, 'show' => $show]);
        if (! $userShow) {
            throw new ShowNotInListException();
        }

        $userShow->setNotificationsEnabled($notificationsEnabled);
        $this->entityManager->persist($userShow);
        $this->entityManager->flush();
    }

    public function addMultipleShows(array $showIds, User $user): void
    {
        if (!$showIds) {
            return;
        }

        foreach ($showIds as $showId) {
            $show = $this->showService->getById((int) $showId);
            if (!$show) {
                continue;
            }
            $showAlreadySaved = $this->entityManager
                ->getRepository(UserShows::class)
                ->findOneBy(['user' => $user, 'show' => $show]);
            if ($showAlreadySaved) {
                continue;
            }

            $userShow = new UserShows();
            $userShow->setUser($user);
            $userShow->setShow($show);
            $this->entityManager->persist($userShow);
        }
        $this->entityManager->flush();
    }

    public function delete(Show $show, User $user): void
    {
        $userShow = $this->entityManager
            ->getRepository(UserShows::class)
            ->findOneBy(['user' => $user, 'show' => $show]);
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

    public function getShowCount(User $user, BackedEnum $genre): int
    {
        $repository = $this->entityManager->getRepository(UserShows::class);

        if ($genre === Genres::Default) {
            return  $repository->count(['user' => $user]);
        }

        $qb = $repository->createQueryBuilder('c');
        $qb->select('count(distinct s)')
            ->where($qb->expr()->eq('c.user', ':userId'))
            ->innerJoin('c.show', 's')
            ->andWhere($qb->expr()->like('s.genres', ':genre'))
            ->setParameter('userId', $user->getId())
            ->setParameter('genre', '%' . $genre->value . '%');


        return $qb->getQuery()->getSingleScalarResult();
    }
}
