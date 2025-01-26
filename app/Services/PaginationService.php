<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\User;
use App\Enum\Genres;
use App\Enum\ShowListSort;
use App\Enum\Sort;
use App\Exception\NotFoundException;
use Doctrine\ORM\NoResultException;

class PaginationService
{
    private int $start = 0;
    private int $length = 20;
    private int $page = 1;
    private int $totalPages = 1;
    private string $sort;
    private string $genre = Genres::Default->value;
    private array $params;
    private ?User $user = null;

    public function __construct(
        private readonly ShowService $showService,
        private readonly UserShowsService $userShowsService
    ) {
    }

    public function getShows(): array
    {
        return $this->showService->getPaginatedShows(
            $this->start,
            $this->length,
            $this->sort,
            $this->genre,
            $this->user
        );
    }

    public function getPagination(): array
    {
        $pagination = ['page' => $this->page, 'totalPages' => $this->totalPages];
        if (isset($this->params['sort'])) {
            $pagination['sort'] = $this->sort;
        }
        if (isset($this->params['genre'])) {
            $pagination['genre'] = $this->genre;
        }

        return $pagination;
    }

    public function discover(array $params): self
    {
        if (isset($params['page'])) {
            $pageNum = (int) $params['page'];
            $this->start = ($pageNum - 1) * $this->length;
            $this->page = $pageNum;
        }

        $this->sort = Sort::Popular->value;
        if (isset($params['sort'])) {
            $this->sort = $params['sort'];
        }

        if (isset($params['genre'])) {
            $this->genre = $params['genre'];
        }

        try {
            $this->totalPages = (int) ceil($this->showService->getShowCount($this->genre) / $this->length);
        } catch (NoResultException $e) {
        }

        if ($this->page > $this->totalPages) {
            throw new NotFoundException();
        }

        $this->params = $params;

        return $this;
    }

    public function showList(array $params, User $user): self
    {

        if (isset($params['page'])) {
            $pageNum = (int) $params['page'];
            $this->start = ($pageNum - 1) * $this->length;
            $this->page = $pageNum;
        }

        $this->sort = ShowListSort::Added->value;
        if (isset($params['sort'])) {
            $this->sort = $params['sort'];
        }

        if (isset($params['genre'])) {
            $this->genre = $params['genre'];
        }

        try {
            $this->totalPages = (int) ceil($this->userShowsService->getShowCount($user, $this->genre) / $this->length);
        } catch (NoResultException $e) {
        }

        if ($this->page > $this->totalPages) {
            throw new NotFoundException();
        }

        $this->params = $params;
        $this->user = $user;

        return $this;
    }
}
