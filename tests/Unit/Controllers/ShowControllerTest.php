<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Contracts\RequestValidatorFactoryInterface;
use App\Contracts\RequestValidatorInterface;
use App\Contracts\SessionInterface;
use App\Controllers\ShowController;
use App\Entity\Show;
use App\Entity\User;
use App\Entity\UserShows;
use App\ResponseFormatter;
use App\Services\PaginationService;
use App\Services\RequestService;
use App\Services\ShowService;
use App\Services\UserShowsService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;

class ShowControllerTest extends TestCase
{
    private const SHOW_ID = 42;
    private const SLUG = 'test-show';

    public function testItOffersNotificationsToAnNtfyUser(): void
    {
        $payload = $this->renderShowPage($this->user(ntfyTopic: 'abc12345'), saved: true);

        $this->assertTrue($payload['notificationsAvailable']);
    }

    public function testItOffersNotificationsToADiscordUser(): void
    {
        $payload = $this->renderShowPage(
            $this->user(discordWebhookUrl: 'https://discord.com/api/webhooks/1/token'),
            saved: true
        );

        $this->assertTrue($payload['notificationsAvailable']);
    }

    public function testItDoesNotOfferNotificationsToAUserWithoutAChannel(): void
    {
        $payload = $this->renderShowPage($this->user(), saved: true);

        $this->assertFalse($payload['notificationsAvailable']);
    }

    public function testItDoesNotOfferNotificationsToGuests(): void
    {
        $payload = $this->renderShowPage(null, saved: false);

        $this->assertFalse($payload['notificationsAvailable']);
    }

    public function testItReportsThePerShowStateOnlyForSavedShows(): void
    {
        $saved = $this->renderShowPage($this->user(ntfyTopic: 'abc12345'), saved: true);
        $notSaved = $this->renderShowPage($this->user(ntfyTopic: 'abc12345'), saved: false);

        $this->assertTrue($saved['notificationsEnabled']);
        $this->assertFalse($notSaved['notificationsEnabled']);
    }

    public function testAGuestXhrWithoutShowsGetsAnEmptyList(): void
    {
        $payload = $this->renderGuestList([], xhr: true);

        $this->assertSame([], $payload['shows']);
        $this->assertSame(
            ['page' => 1, 'totalPages' => 1, 'showCount' => 0],
            $payload['pagination']
        );
    }

    public function testAGuestXhrIgnoresNonNumericShows(): void
    {
        $payload = $this->renderGuestList(['shows' => ['abc', '12x']], xhr: true);

        $this->assertSame([], $payload['shows']);
    }

    public function testAGuestPageWithoutShowsStillRendersTheShell(): void
    {
        $payload = $this->renderGuestList([], xhr: false);

        $this->assertSame([], $payload['shows']);
        $this->assertSame(
            ['page' => 1, 'totalPages' => 1, 'showCount' => 0],
            $payload['pagination']
        );
    }

    /**
     * A guest list that resolves to nothing must short-circuit before the
     * pagination service, which would otherwise return the whole catalogue.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> The payload handed to Twig or the JSON body.
     */
    private function renderGuestList(array $params, bool $xhr): array
    {
        $payload = [];

        $validator = $this->createMock(RequestValidatorInterface::class);
        $validator->method('validate')->willReturn($params);

        $validatorFactory = $this->createMock(RequestValidatorFactoryInterface::class);
        $validatorFactory->method('make')->willReturn($validator);

        $paginationService = $this->createMock(PaginationService::class);
        $paginationService->expects($this->never())->method('showlist');

        $twig = $this->createMock(Twig::class);
        $twig->expects($xhr ? $this->never() : $this->once())
            ->method('render')
            ->willReturnCallback(
                function ($response, string $template, array $data) use (&$payload) {
                    $this->assertSame('showlist/index.twig', $template);
                    $payload = $data;

                    return $response;
                }
            );

        $responseFormatter = $this->createMock(ResponseFormatter::class);
        $responseFormatter->expects($xhr ? $this->once() : $this->never())
            ->method('asJSON')
            ->willReturnCallback(
                function ($response, int $status, array $data) use (&$payload) {
                    $this->assertSame(200, $status);
                    $payload = $data;

                    return $response;
                }
            );

        $controller = new ShowController(
            $twig,
            $this->createMock(ShowService::class),
            $paginationService,
            $validatorFactory,
            $responseFormatter,
            $this->createMock(UserShowsService::class),
            new RequestService($this->createMock(SessionInterface::class)),
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.test/showlist');

        if ($xhr) {
            $request = $request->withHeader('X-Requested-With', 'XMLHttpRequest');
        }

        $controller->index($request, new Response());

        return $payload;
    }

    /**
     * @return array<string, mixed> The data the controller hands to Twig.
     */
    private function renderShowPage(?User $user, bool $saved): array
    {
        $payload = [];

        $twig = $this->createMock(Twig::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(
                function ($response, string $template, array $data) use (&$payload) {
                    $this->assertSame('show/index.twig', $template);
                    $payload = $data;

                    return $response;
                }
            );

        $show = $this->show();

        $showService = $this->createMock(ShowService::class);
        $showService->method('getById')->with(self::SHOW_ID)->willReturn($show);
        $showService->method('slugify')->with('Test Show')->willReturn(self::SLUG);

        $validator = $this->createMock(RequestValidatorInterface::class);
        $validator->method('validate')->willReturn(['showId' => self::SHOW_ID, 'slug' => self::SLUG]);

        $validatorFactory = $this->createMock(RequestValidatorFactoryInterface::class);
        $validatorFactory->method('make')->willReturn($validator);

        $userShowsService = $this->createMock(UserShowsService::class);
        if ($user === null) {
            $userShowsService->expects($this->never())->method('get');
        } else {
            $userShowsService->method('get')
                ->with($user)
                ->willReturn($saved ? [$this->userShows($show, $user)] : []);
        }

        $controller = new ShowController(
            $twig,
            $showService,
            $this->createMock(PaginationService::class),
            $validatorFactory,
            $this->createMock(ResponseFormatter::class),
            $userShowsService,
            $this->createMock(RequestService::class),
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.test/shows/' . self::SHOW_ID . '-' . self::SLUG);

        if ($user !== null) {
            $request = $request->withAttribute('user', $user);
        }

        $controller->get($request, new Response(), ['showId' => (string) self::SHOW_ID, 'slug' => self::SLUG]);

        return $payload;
    }

    private function show(): Show
    {
        $show = new Show();
        $show->setName('Test Show');

        (new ReflectionProperty(Show::class, 'id'))->setValue($show, self::SHOW_ID);

        return $show;
    }

    private function user(?string $ntfyTopic = null, ?string $discordWebhookUrl = null): User
    {
        $user = new User();
        $user->setNtfyTopic($ntfyTopic);
        $user->setDiscordWebhookUrl($discordWebhookUrl);

        return $user;
    }

    private function userShows(Show $show, User $user): UserShows
    {
        $userShows = new UserShows();
        $userShows->setShow($show);
        $userShows->setUser($user);
        $userShows->setNotificationsEnabled(true);

        return $userShows;
    }
}