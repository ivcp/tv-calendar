<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CalendarService;
use DateTime;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class CalendarController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly CalendarService $calendarService,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {

        $month = (new DateTime('now'))->format('Y-m');
        $schedule = $this->calendarService->getSchedule($month);

        return $this->twig->render(
            $response,
            'calendar.twig',
            ['schedule' => json_encode($schedule), 'month' => 'now']
        );
    }

    public function getMonth(Request $request, Response $response): Response
    {

        $month = $request->getAttribute('year') . '-' . $request->getAttribute('month');
        $schedule = $this->calendarService->getSchedule($month);

        return $this->twig->render(
            $response,
            'calendar.twig',
            ['schedule' => json_encode($schedule), 'month' => $month]
        );
    }
}
