<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\RequestValidatorFactoryInterface;
use App\DataObjects\ShowCardData;
use App\Exception\NotFoundException;
use App\Exception\ShowNotInListException;
use App\RequestValidators\DiscoverRequestValidator;
use App\RequestValidators\GetShowRequestValidator;
use App\RequestValidators\ShowListRequestValidator;
use App\RequestValidators\ShowRequestValidator;
use App\ResponseFormatter;
use App\Services\PaginationService;
use App\Services\RequestService;
use App\Services\ShowService;
use App\Services\UserShowsService;
use Doctrine\DBAL\Exception\DriverException;
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
        private readonly UserShowsService $userShowsService,
        private readonly RequestService $requestService
    ) {
    }

    public function index(Request $request, Response $response): Response
    {

        $params = $this->requestValidatorFactory
        ->make(ShowListRequestValidator::class)
        ->validate($request->getQueryParams());


        $user = $request->getAttribute('user');
        $showList = $this->paginationService->showlist($params, $user);

        $shows = array_map(fn ($show) => new ShowCardData(
            id:$show->getId(),
            name:$show->getName(),
            imageMedium: $show->getImageMedium()
        ), $showList->getShows());

        if ($this->requestService->isXhr($request)) {
            return $this->responseFormatter->asJSON(
                $response,
                200,
                [
                    'shows' => $shows,
                    'pagination' => $showList->getPagination(),
                ]
            );
        }

        return $this->twig->render(
            $response,
            'shows/index.twig',
            [
                'shows' => $shows,
                'pagination' => $showList->getPagination(),
            ]
        );
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $params = $this->requestValidatorFactory
        ->make(GetShowRequestValidator::class)
        ->validate($args);

        $showId = (int) $params['showId'];

        try {
            $show = $this->showService->getById($showId);
        } catch (DriverException $e) {
            throw new NotFoundException();
        }
        if (! $show) {
            throw new NotFoundException();
        }


        return $this->twig->render(
            $response,
            'shows/show.twig',
            [
                'show' =>   $show,
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

        $shows = array_map(fn ($show) => new ShowCardData(
            id:$show->getId(),
            name:$show->getName(),
            imageMedium: $show->getImageMedium()
        ), $discover->getShows());

        return $this->twig->render(
            $response,
            'shows/discover.twig',
            [
                'shows' =>   $shows,
                'pagination' => $discover->getPagination(),
                'userShows' => $userShows,
            ]
        );
    }

    public function store(Request $request, Response $response): Response
    {
        $params = $this->requestValidatorFactory
        ->make(ShowRequestValidator::class)
        ->validate($request->getParsedBody());


        $showId = (int) $params['showId'];

        $show = $this->showService->getById($showId);
        if (! $show) {
            return $this->responseFormatter->asJSONMessage(
                $response,
                404,
                "show with id $showId does not exist"
            );
        }

        $user = $request->getAttribute('user');
        try {
            $this->userShowsService->add($show, $user);
        } catch (UniqueConstraintViolationException $e) {
            return $this->responseFormatter->asJSONMessage($response, 400, "show already added");
        }

        return $this->responseFormatter->asJSONMessage($response, 200, $show->getName() . ' added');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $args = $this->requestValidatorFactory
        ->make(ShowRequestValidator::class)
        ->validate($args);

        $showId = (int) $args['showId'];
        $show = $this->showService->getById($showId);

        if (! $show) {
            return $this->responseFormatter->asJSONMessage(
                $response,
                404,
                "show with id $showId does not exist"
            );
        }

        $user = $request->getAttribute('user');
        try {
            $this->userShowsService->delete($show, $user);
        } catch (ShowNotInListException $e) {
            return $this->responseFormatter->asJSONMessage($response, 400, "show not in your list");
        }

        return $this->responseFormatter->asJSONMessage($response, 200, $show->getName(). ' removed from your list');
    }
}
