<?php

declare(strict_types=1);

namespace App\Controllers;

use DateTime;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class CalendarController
{
    public function __construct(private readonly Twig $twig) {}

    public function index(Request $request, Response $response): Response
    {
        $contents = file_get_contents(STORAGE_PATH . '/schedule.json');
        $data = json_decode($contents);

        $currentMonth = new DateTime('now');
        $monthNumber = $currentMonth->format('n');
        $daysInMonth = $currentMonth->format('t');



        $popular = array_values(array_filter(
            $data,
            fn($show) =>
            $show->_embedded->show->weight === 100 && (new DateTime($show->airdate))->format('n') === $monthNumber
        ));

        $popularJSON = json_encode($popular);



        return $this->twig->render(
            $response,
            'calendar.twig',
            ['daysInMonth' => $daysInMonth, 'schedule' => $popularJSON, 'month' => 'now']
        );
    }

    public function getMonth(Request $request, Response $response): Response
    {

        $contents = file_get_contents(STORAGE_PATH . '/schedule.json');
        $data = json_decode($contents);

        $month = $request->getAttribute('year') . '-' . $request->getAttribute('month');

        $currentMonth = new DateTime($month);
        $monthNumber = $currentMonth->format('n');
        $daysInMonth = $currentMonth->format('t');


        $popular = array_values(array_filter(
            $data,
            fn($show) =>
            $show->_embedded->show->weight === 100 && (new DateTime($show->airdate))->format('Y-m') === $month
        ));

        $popularJSON = json_encode($popular);


        return $this->twig->render(
            $response,
            'calendar.twig',
            ['daysInMonth' => $daysInMonth, 'schedule' => $popularJSON, 'month' => $month]
        );
    }
}
