<?php

declare(strict_types=1);

namespace App\Controllers;

use DateTime;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class HomeController
{
    public function __construct(private readonly Twig $twig) {}

    public function index(Request $request, Response $response): Response
    {
        // $contents = file_get_contents(STORAGE_PATH . '/schedule.json');
        // $data = json_decode($contents);


        // $currentMonth = (new DateTime('now'))->format('n');

        // $popular = array_values(array_filter(
        //     $data,
        //     fn($show) =>
        //     $show->_embedded->show->weight === 100 && (new DateTime($show->airdate))->format('n') === $currentMonth
        // ));


        // var_dump($popular);
        // exit;

        return $this->twig->render($response, 'dashboard.twig');
    }
}
