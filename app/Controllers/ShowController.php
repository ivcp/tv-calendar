<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\RequestValidatorFactoryInterface;
use App\RequestValidators\DiscoverRequestValidator;
use App\Services\PaginationService;
use App\Services\ShowService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class ShowController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly ShowService $showService,
        private readonly PaginationService $paginationService,
        private readonly RequestValidatorFactoryInterface $requestValidatorFactory
    ) {
    }

    public function discover(Request $request, Response $response): Response
    {
        $params = $this->requestValidatorFactory
        ->make(DiscoverRequestValidator::class)
        ->validate($request->getQueryParams());

        $discover = $this->paginationService->get($params);

        return $this->twig->render(
            $response,
            'shows/discover.twig',
            ['shows' => $discover->getShows(), 'pagination' => $discover->getPagination()]
        );
    }
}
