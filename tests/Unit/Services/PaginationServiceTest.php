<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entity\User;
use App\Enum\Genres;
use App\Services\PaginationService;
use App\Services\ShowService;
use App\Services\UserShowsService;
use PHPUnit\Framework\TestCase;

final class PaginationServiceTest extends TestCase
{
    public function testGuestCountUsesOnlyExistingShows(): void
    {
        $showService = $this->createMock(ShowService::class);
        $showService->expects($this->once())
            ->method('getShowCountByIds')
            ->with(['10', '20'], Genres::Default)
            ->willReturn(1);

        $pagination = new PaginationService(
            $showService,
            $this->createMock(UserShowsService::class)
        );

        $pagination->showList(['shows' => ['10', '20']], null);

        $this->assertSame(1, $pagination->getPagination()['showCount']);
        $this->assertSame(1, $pagination->getPagination()['totalPages']);
    }

    public function testGuestCountAppliesTheSelectedGenre(): void
    {
        $showService = $this->createMock(ShowService::class);
        $showService->expects($this->once())
            ->method('getShowCountByIds')
            ->with(['10', '20'], Genres::Drama)
            ->willReturn(1);

        $pagination = new PaginationService(
            $showService,
            $this->createMock(UserShowsService::class)
        );

        $pagination->showList(['shows' => ['10', '20'], 'genre' => 'Drama'], null);

        $this->assertSame(1, $pagination->getPagination()['showCount']);
        $this->assertSame('Drama', $pagination->getPagination()['genre']);
    }

    public function testAuthenticatedCountStillComesFromUserShows(): void
    {
        $user = $this->createMock(User::class);

        $showService = $this->createMock(ShowService::class);
        $showService->expects($this->never())->method('getShowCountByIds');

        $userShowsService = $this->createMock(UserShowsService::class);
        $userShowsService->expects($this->once())
            ->method('getShowCount')
            ->with($user, Genres::Default)
            ->willReturn(5);

        $pagination = new PaginationService($showService, $userShowsService);
        $pagination->showList([], $user);

        $this->assertSame(5, $pagination->getPagination()['showCount']);
    }
}
