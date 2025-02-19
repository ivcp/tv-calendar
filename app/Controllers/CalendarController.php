<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\ResponseFormatter;
use App\Services\CalendarService;
use App\Services\RequestService;
use DateTime;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class CalendarController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly CalendarService $calendarService,
        private readonly Config $config,
        private readonly RequestService $requestService,
        private readonly ResponseFormatter $responseFormatter
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->twig->render(
            $response,
            'calendar/index.twig'
        );
    }

    public function getMonth(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $month = $request->getAttribute('year') . '-' . $request->getAttribute('month');
        $schedule = $this->calendarService->getSchedule($month, $user);

        if ($this->requestService->isXhr($request)) {
            return $this->responseFormatter->asJSON($response, 200, ['schedule' => $schedule]);
        }

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
