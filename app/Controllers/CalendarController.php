<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\RequestValidatorFactoryInterface;
use App\RequestValidators\ScheduleRequestValidator;
use App\ResponseFormatter;
use App\Services\CalendarService;
use App\Services\RequestService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class CalendarController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly CalendarService $calendarService,
        private readonly RequestService $requestService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly RequestValidatorFactoryInterface $requestValidatorFactory,
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

        if ($this->requestService->isXhr($request)) {
            $args = $this->requestValidatorFactory
            ->make(ScheduleRequestValidator::class)
            ->validate($request->getQueryParams());

            $episodes = $this->calendarService->getSchedule(
                $month,
                $args['tz'],
                $args['schedule'],
                $user,
                isset($args['shows']) ? $args['shows'] : []
            );
            return $this->responseFormatter->asJSON($response, 200, $episodes);
        }

        return $this->twig->render(
            $response,
            'calendar/index.twig',
            [
                'month' => $month
            ]
        );
    }
}
