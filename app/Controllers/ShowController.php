<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\RequestValidatorFactoryInterface;
use App\Exception\ShowNotInListException;
use App\RequestValidators\DeleteShowRequestValidator;
use App\RequestValidators\DiscoverRequestValidator;
use App\RequestValidators\ShowListRequestValidator;
use App\RequestValidators\StoreShowRequestValidator;
use App\ResponseFormatter;
use App\Services\PaginationService;
use App\Services\ShowService;
use App\Services\UserShowsService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

    public function index(Request $request, Response $response): Response
    {

        $params = $this->requestValidatorFactory
        ->make(ShowListRequestValidator::class)
        ->validate($request->getQueryParams());


        $user = $request->getAttribute('user');
        $showList = $this->paginationService->showlist($params, $user);

        return $this->twig->render(
            $response,
            'shows/index.twig',
            [
                'shows' => $showList->getShows(),
                'pagination' => $showList->getPagination(),
            ]
        );
    }

    public function discover(Request $request, Response $response): Response
    {
        $params = $this->requestValidatorFactory
        ->make(DiscoverRequestValidator::class)
        ->validate($request->getQueryParams());

        $discover = $this->paginationService->discover($params);


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


        $showId = (int) $params['showId'];

        $show = $this->showService->getById($showId);
        if (! $show) {
            return $this->responseFormatter->asJSON(
                $response,
                404,
                "show with id $showId does not exist"
            );
        }

        $user = $request->getAttribute('user');
        try {
            $this->userShowsService->add($show, $user);
        } catch (UniqueConstraintViolationException $e) {
            return $this->responseFormatter->asJSON($response, 400, "show already added");
        }

        return $this->responseFormatter->asJSON($response, 200, $show->getName() . ' added');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $args = $this->requestValidatorFactory
        ->make(DeleteShowRequestValidator::class)
        ->validate($args);

        $showId = (int) $args['id'];
        $show = $this->showService->getById($showId);

        if (! $show) {
            return $this->responseFormatter->asJSON(
                $response,
                404,
                "show with id $showId does not exist"
            );
        }

        $user = $request->getAttribute('user');
        try {
            $this->userShowsService->delete($show, $user);
        } catch (ShowNotInListException $e) {
            return $this->responseFormatter->asJSON($response, 400, "show not in your list");
        }

        return $this->responseFormatter->asJSON($response, 200, $show->getName(). ' removed from your list');
    }
}
