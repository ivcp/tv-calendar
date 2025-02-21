<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
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
        private readonly Config $config
    ) {
    }

    public function index(Request $request, Response $response): Response
    {

        $month = (new DateTime('now'))->format('Y-m');
        $user = $request->getAttribute('user');
        $schedule = $this->calendarService->getSchedule($month, $user);


        return $this->twig->render(
            $response,
            'calendar/index.twig',
            [
                'schedule' => json_encode($schedule, $this->config->get('json_tags')),
                'month' => 'now'
            ]
        );
    }

    public function getMonth(Request $request, Response $response): Response
    {

        $month = $request->getAttribute('year') . '-' . $request->getAttribute('month');
        $user = $request->getAttribute('user');
        $schedule = $this->calendarService->getSchedule($month, $user);

        return $this->twig->render(
            $response,
            'calendar/index.twig',
            [
                'schedule' => json_encode($schedule, $this->config->get('json_tags')),
                'month' => $month
            ]
        );
    }
}
