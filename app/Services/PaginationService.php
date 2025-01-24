<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Genres;
use App\Enum\Sort;
use App\Exception\NotFoundException;

class PaginationService
{
    private int $start = 0;
    private int $length = 20;
    private int $page = 1;
    private int $totalPages = 0;
    private string $sort = Sort::Popular->value;
    private string $genre = Genres::Default->value;
    private array $params;

    public function __construct(
        private readonly ShowService $showService
    ) {
    }

    public function getShows(): array
    {
        return $this->showService->getPaginatedShows(
            $this->start,
            $this->length,
            $this->sort,
            $this->genre
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

    public function get(array $params): self
    {
        if (isset($params['page'])) {
            $pageNum = (int) $params['page'];
            $this->start = ($pageNum - 1) * $this->length;
            $this->page = $pageNum;
        }

        $this->totalPages = (int) ceil($this->showService->getShowCount() / $this->length);
        if ($this->page > $this->totalPages) {
            throw new NotFoundException();
        }

        if (isset($params['sort'])) {
            $this->sort = $params['sort'];
        }

        if (isset($params['genre'])) {
            $this->genre = $params['genre'];
        }

        $this->params = $params;

        return $this;
    }
}
