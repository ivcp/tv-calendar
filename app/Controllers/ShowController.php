<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ShowService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class ShowController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly ShowService $showService
    ) {
    }

    public function discover(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $start = 0;
        $length = 20;
        if (isset($params['page']) && is_numeric($params['page'])) {
            $page = (int) $params['page'];
            if ($page > 0) {
                $start = ($page - 1) * $length;
            }
        }

        $sort = 'popular';
        $sortOptions = ['popular', 'new'];
        if (isset($params['sort']) && in_array($params['sort'], $sortOptions)) {
            $sort = $params['sort'];
        }

        $shows =  $this->showService->getPaginatedShows($start, $length, $sort === 'new');


        //var_dump($shows);
        //pass to twig
        //query string to filter by genre, new, popular...
        return $this->twig->render($response, 'shows/discover.twig', ['shows' => $shows]);
    }
}
