<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\RequestValidatorFactoryInterface;
use App\RequestValidators\DiscoverRequestValidator;
use App\RequestValidators\StoreShowRequestValidator;
use App\ResponseFormatter;
use App\Services\PaginationService;
use App\Services\ShowService;
use App\Services\UserShowsService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class ShowController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly ShowService $showService,
        private readonly PaginationService $paginationService,
        private readonly RequestValidatorFactoryInterface $requestValidatorFactory,
        private readonly ResponseFormatter $responseFormatter,
        private readonly UserShowsService $userShowsService
    ) {
    }

    public function discover(Request $request, Response $response): Response
    {
        $params = $this->requestValidatorFactory
        ->make(DiscoverRequestValidator::class)
        ->validate($request->getQueryParams());

        $discover = $this->paginationService->get($params);


        $user = $request->getAttribute('user');
        $userShows = [];
        if ($user) {
            $shows = $this->userShowsService->get($user);
            $userShows = array_map(fn ($us) => $us->getShow()->getId(), $shows);
        }

        return $this->twig->render(
            $response,
            'shows/discover.twig',
            [
                'shows' => $discover->getShows(),
                'pagination' => $discover->getPagination(),
                'userShows' => $userShows,
            ]
        );
    }

    public function store(Request $request, Response $response): Response
    {
        $params = $this->requestValidatorFactory
        ->make(StoreShowRequestValidator::class)
        ->validate($request->getParsedBody());

        $user = $request->getAttribute('user');
        if (! $user) {
            return $this->responseFormatter->asJSON($response->withStatus(403), ['error' => 'log in to add shows']);
        }
        $showId = (int) $params['showId'];

        $show = $this->showService->getById($showId);
        if (! $show) {
            return $this->responseFormatter->asJSON(
                $response->withStatus(404),
                ['error' => "show with id $showId does not exist"]
            );
        }

        $this->userShowsService->add($show, $user);

        return $this->responseFormatter->asJSON($response, ['success' => $show->getName() . ' added!']);
    }
}
