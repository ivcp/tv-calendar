<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\RequestValidatorFactoryInterface;
use App\Enum\Genres;
use App\Exception\BadRequestException;
use App\RequestValidators\DiscoverRequestValidator;
use App\Services\ShowService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class ShowController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly ShowService $showService,
        private readonly RequestValidatorFactoryInterface $requestValidatorFactory
    ) {
    }

    public function discover(Request $request, Response $response): Response
    {
        $start = 0;
        $length = 20;
        $page = 1;

        $params = $this->requestValidatorFactory
        ->make(DiscoverRequestValidator::class)
        ->validate($request->getQueryParams());


        if (isset($params['page'])) {
            $pageNum = (int) $params['page'];
            $start = ($pageNum - 1) * $length;
            $page = $pageNum;
        }

        $sort = 'popular';

        if (isset($params['sort'])) {
            $sort = $params['sort'];
        }

        $totalPages =  ceil($this->showService->getShowCount() / $length);
        if ($page > $totalPages) {
            throw new BadRequestException();
        }

        $genre = Genres::Default->value;
        if (isset($params['genre'])) {
            $genre = $params['genre'];
        }

        $shows =  $this->showService->getPaginatedShows($start, $length, $sort === 'new', $genre);

        $pagination = ['page' => $page, 'totalPages' => $totalPages];
        if (isset($params['sort'])) {
            $pagination['sort'] = $sort;
        }
        if (isset($params['genre'])) {
            $pagination['genre'] = $genre;
        }

        return $this->twig->render(
            $response,
            'shows/discover.twig',
            ['shows' => $shows, 'pagination' => $pagination]
        );
    }
}
