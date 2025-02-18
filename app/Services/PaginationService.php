<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\User;
use App\Enum\DiscoverSort;
use App\Enum\Genres;
use App\Enum\ShowListSort;
use App\Exception\NotFoundException;
use BackedEnum;

class PaginationService
{
    private int $start = 0;
    private int $length = 20;
    private int $page = 1;
    private int $totalPages = 1;
    private int $showCount = 0;
    private BackedEnum $sort;
    private BackedEnum $genre = Genres::Default;
    private array $params;
    private ?User $user = null;
    private ?string $query = null;

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
            $this->user,
            $this->query
        );
    }

    public function getPagination(): array
    {
        $pagination = [
            'page' => $this->page,
            'totalPages' => $this->totalPages,
            'showCount' => $this->showCount
        ];
        if (isset($this->params['sort'])) {
            $pagination['sort'] = $this->sort->value;
        }
        if (isset($this->params['genre'])) {
            $pagination['genre'] = $this->genre->value;
        }

        return $pagination;
    }


    public function discover(array $params): self
    {
        $this->setStartAndPage($params);
        $this->setSort(DiscoverSort::class, $params);
        $this->setGenre($params);

        $count = $this->showService->getShowCount($this->genre);
        if ($count) {
            $this->totalPages = (int) ceil($count / $this->length);
        }

        if ($this->page > $this->totalPages) {
            throw new NotFoundException();
        }

        $this->params = $params;

        return $this;
    }

    public function showList(array $params, User $user): self
    {

        $this->setStartAndPage($params);
        $this->setSort(ShowListSort::class, $params);
        $this->setGenre($params);


        $count = $this->userShowsService->getShowCount($user, $this->genre);
        if ($count) {
            $this->totalPages = (int) ceil($count / $this->length);
            $this->showCount = $count;
        }

        if ($this->page > $this->totalPages) {
            throw new NotFoundException();
        }

        $this->params = $params;
        $this->user = $user;

        return $this;
    }

    public function search(array $params): self
    {
        $this->length = 10;
        $this->setStartAndPage($params);
        $this->setSort(DiscoverSort::class, $params);

        $query = trim($params['query']);

        $count = $this->showService->getShowCount($this->genre, $query);
        if ($count) {
            $this->totalPages = (int) ceil($count / $this->length);
            $this->showCount = $count;
        }

        if ($this->page > $this->totalPages) {
            throw new NotFoundException();
        }

        $this->params = $params;
        $this->query = $query;

        return $this;
    }

    private function setStartAndPage(array $params): void
    {
        if (isset($params['page'])) {
            $pageNum = (int) $params['page'];
            $this->start = ($pageNum - 1) * $this->length;
            $this->page = $pageNum;
        }
    }
    private function setSort(string $sort, array $params): void
    {
        $this->sort = $sort::default();
        if (isset($params['sort'])) {
            $this->sort = $sort::tryFrom($params['sort']);
        }
    }
    private function setGenre(array $params): void
    {
        if (isset($params['genre'])) {
            $this->genre = Genres::tryFrom($params['genre']);
        }
    }
}
