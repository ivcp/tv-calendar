<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Contracts\RequestValidatorFactoryInterface;
use App\Controllers\CalendarController;
use App\ResponseFormatter;
use App\Services\CalendarService;
use App\Services\RequestService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;

class CalendarControllerTest extends TestCase
{
    public function testItIndexesTheCurrentMonth(): void
    {
        $payload = $this->renderMonth($this->month(0));

        $this->assertNull($payload['page_robots']);
    }

    public function testItIndexesTheMonthTwoMonthsAgo(): void
    {
        $payload = $this->renderMonth($this->month(-2));

        $this->assertNull($payload['page_robots']);
    }

    public function testItNoindexesTheMonthThreeMonthsAgo(): void
    {
        $payload = $this->renderMonth($this->month(-3));

        $this->assertSame('noindex, follow', $payload['page_robots']);
    }

    public function testItIndexesTheMonthTwoMonthsAhead(): void
    {
        $payload = $this->renderMonth($this->month(2));

        $this->assertNull($payload['page_robots']);
    }

    public function testItNoindexesTheMonthThreeMonthsAhead(): void
    {
        $payload = $this->renderMonth($this->month(3));

        $this->assertSame('noindex, follow', $payload['page_robots']);
    }

    public function testItNoindexesTheEdgesOfTheRouteRange(): void
    {
        $payload = $this->renderMonth('1920-01');

        $this->assertSame('noindex, follow', $payload['page_robots']);
    }

    public function testItStillPassesTheMonthToTheTemplate(): void
    {
        $month = $this->month(-1);

        $this->assertSame($month, $this->renderMonth($month)['month']);
    }

    private function month(int $offset): string
    {
        return (new DateTimeImmutable('first day of this month'))
            ->modify(sprintf('%+d months', $offset))
            ->format('Y-m');
    }

    /**
     * @return array<string, mixed> The data the controller hands to Twig.
     */
    private function renderMonth(string $month): array
    {
        $payload = [];

        $twig = $this->createMock(Twig::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(
                function ($response, string $template, array $data) use (&$payload) {
                    $this->assertSame('calendar/index.twig', $template);
                    $payload = $data;

                    return $response;
                }
            );

        $requestService = $this->createMock(RequestService::class);
        $requestService->method('isXhr')->willReturn(false);

        $controller = new CalendarController(
            $twig,
            $this->createMock(CalendarService::class),
            $requestService,
            $this->createMock(ResponseFormatter::class),
            $this->createMock(RequestValidatorFactoryInterface::class),
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/' . $month)
            ->withAttribute('year', substr($month, 0, 4))
            ->withAttribute('month', substr($month, 5, 2));

        $controller->getMonth($request, new Response());

        return $payload;
    }
}
