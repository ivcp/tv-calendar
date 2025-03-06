<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class SettingsController
{
    public function __construct(
        private readonly Twig $twig
    ) {
    }
    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        return $this->twig->render(
            $response,
            'settings/index.twig',
            [
                'email' => $user ? $user->getEmail() : null,
                'verified' => $user ? $user->getVerifiedAt() : null
            ]
        );
    }

}
