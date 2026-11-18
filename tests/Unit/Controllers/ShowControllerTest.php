<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Config;
use App\Contracts\RequestValidatorFactoryInterface;
use App\Contracts\RequestValidatorInterface;
use App\Contracts\SessionInterface;
use App\Controllers\ShowController;
use App\Entity\Episode;
use App\Entity\Show;
use App\Entity\User;
use App\Entity\UserShows;
use App\ResponseFormatter;
use App\Services\PaginationService;
use App\Services\RequestService;
use App\Services\ShowPageService;
use App\Services\ShowService;
use App\Services\UserShowsService;
use DateTime;
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

    public function testItNoindexesThinShowPages(): void
    {
        $payload = $this->renderShowPage(null, saved: false, thin: true);

        $this->assertSame('noindex, follow', $payload['page_robots']);
    }

    public function testItDoesNotNoindexIndexableShowPages(): void
    {
        $payload = $this->renderShowPage(null, saved: false);

        $this->assertNull($payload['page_robots']);
    }

    public function testItBuildsTheShowPageSummaryAndSeasonData(): void
    {
        $payload = $this->renderShowPage(null, saved: false);

        $this->assertSame('Test & show', $payload['showSummary']);
        $this->assertSame('(2019)', $payload['years']);
        $this->assertSame([2, 1], array_column($payload['seasons'], 'number'));
        $this->assertSame(2, $payload['currentSeason']);
        $this->assertSame('Future Episode', $payload['nextEpisode']->episodeName);
        $this->assertSame('Past Episode', $payload['lastEpisode']->episodeName);
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

    public function testItDoesNotNoindexTheFirstDiscoverPage(): void
    {
        $payload = $this->renderDiscover(1);

        $this->assertNull($payload['page_robots']);
    }

    public function testItNoindexesDeeperDiscoverPages(): void
    {
        $payload = $this->renderDiscover(2);

        $this->assertSame('noindex, follow', $payload['page_robots']);
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
            $this->showPageService(),
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
    private function renderDiscover(int $page): array
    {
        $payload = [];

        $twig = $this->createMock(Twig::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(
                function ($response, string $template, array $data) use (&$payload) {
                    $this->assertSame('discover/index.twig', $template);
                    $payload = $data;

                    return $response;
                }
            );

        $validator = $this->createMock(RequestValidatorInterface::class);
        $validator->method('validate')->willReturn(['page' => (string) $page]);

        $validatorFactory = $this->createMock(RequestValidatorFactoryInterface::class);
        $validatorFactory->method('make')->willReturn($validator);

        $paginationService = $this->createMock(PaginationService::class);
        $paginationService->method('discover')->willReturnSelf();
        $paginationService->method('getShows')->willReturn([]);
        $paginationService->method('getPagination')->willReturn([
            'page' => $page,
            'totalPages' => 10,
            'showCount' => 200,
        ]);

        $userShowsService = $this->createMock(UserShowsService::class);
        $userShowsService->expects($this->never())->method('get');

        $controller = new ShowController(
            $twig,
            $this->createMock(ShowService::class),
            $paginationService,
            $validatorFactory,
            $this->createMock(ResponseFormatter::class),
            $userShowsService,
            new RequestService($this->createMock(SessionInterface::class)),
            $this->showPageService(),
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.test/discover?page=' . $page);

        $controller->discover($request, new Response());

        return $payload;
    }

    /**
     * @return array<string, mixed> The data the controller hands to Twig.
     */
    private function renderShowPage(?User $user, bool $saved, bool $thin = false): array
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
        $showService->method('isThinShow')->with($show)->willReturn($thin);

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
            $this->showPageService(),
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
        $show->setSummary('<p>Test &amp; show</p>');
        $show->setPremiered('2019-09-01');
        $show->setNetworkName('HBO');

        (new ReflectionProperty(Show::class, 'id'))->setValue($show, self::SHOW_ID);
        (new ReflectionProperty(Show::class, 'ended'))->setValue($show, null);
        (new ReflectionProperty(Show::class, 'webChannelName'))->setValue($show, null);

        $this->episode($show, 1, 'Past Episode', 1, 1, '-10 days');
        $this->episode($show, 2, 'Future Episode', 2, 1, '+10 days');

        return $show;
    }

    private function episode(
        Show $show,
        int $id,
        string $name,
        int $season,
        int $number,
        string $airstamp
    ): Episode {
        $episode = new Episode();
        (new ReflectionProperty(Episode::class, 'id'))->setValue($episode, $id);

        $episode->setTvMazeEpisodeId($id);
        $episode->setName($name);
        $episode->setSummary('<p>Episode summary</p>');
        $episode->setSeason($season);
        $episode->setNumber($number);
        $episode->setAirstamp(new DateTime($airstamp));
        $episode->setType('regular');
        $episode->setImageMedium('https://static.tvmaze.com/medium.jpg');
        $episode->setShow($show);

        return $episode;
    }

    private function showPageService(): ShowPageService
    {
        return new ShowPageService(new Config(['app_url' => 'https://example.test']));
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