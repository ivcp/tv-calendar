<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class ShowController
{
    public function __construct(
        private readonly Twig $twig
    ) {
    }

    public function discover(Request $request, Response $response): Response
    {
        //get pupular shows
        //pass to twig
        //query string to filter by genre, new, popular...
        return $this->twig->render($response, 'shows/discover.twig');
    }
}
