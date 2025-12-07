<?php

declare(strict_types=1);

namespace App\Controllers;

use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AboutController
{
    public function __construct(
        private readonly Twig $twig,
    ) {
    }

    public function about(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'about/about.twig');
    }

    public function privacy(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'about/privacy.twig');
    }

    public function terms(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'about/terms.twig');
    }

    public function notifications(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'about/notifications.twig');
    }
}
