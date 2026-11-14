<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Contracts\RequestValidatorFactoryInterface;
use App\Contracts\RequestValidatorInterface;
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