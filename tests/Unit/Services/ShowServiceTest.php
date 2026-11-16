<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entity\Episode;
use App\Entity\Show;
use App\Services\ShowService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

class ShowServiceTest extends TestCase
{
    public function testItTreatsAShowWithoutSummaryAsThin(): void
    {
        $show = $this->show(summary: null);

        $this->assertTrue($this->service()->isThinShow($show));
    }

    public function testItTreatsAWhitespaceOnlySummaryAsThin(): void
    {
        $show = $this->show(summary: "  \n ");

        $this->assertTrue($this->service()->isThinShow($show));
    }

    public function testItTreatsAShowWithoutPosterAsThin(): void
    {
        $show = $this->show(imageMedium: null, imageOriginal: null);

        $this->assertTrue($this->service()->isThinShow($show));
    }

    public function testItTreatsAShowWithAFallbackPosterAsIndexable(): void
    {
        $show = $this->show(imageMedium: null);

        $this->assertFalse($this->service()->isThinShow($show));
    }

    public function testItTreatsAShowWithTooFewEpisodesAsThin(): void
    {
        $show = $this->show(episodes: ShowService::MIN_INDEXABLE_EPISODES - 1);

        $this->assertTrue($this->service()->isThinShow($show));
    }

    public function testItTreatsAShowWithSummaryPosterAndEnoughEpisodesAsIndexable(): void
    {
        $show = $this->show(episodes: ShowService::MIN_INDEXABLE_EPISODES);

        $this->assertFalse($this->service()->isThinShow($show));
    }

    public function testItSlugifiesPlainNames(): void
    {
        $this->assertSame('9-1-1', $this->service()->slugify('9-1-1'));
        $this->assertSame('schitt-s-creek', $this->service()->slugify("Schitt's Creek"));
        $this->assertSame('law-order-svu', $this->service()->slugify('Law & Order: SVU'));
    }

    #[RequiresPhpExtension('intl')]
    public function testItTransliteratesAccentedAndCyrillicNames(): void
    {
        $service = $this->service();

        $this->assertSame('cerny-friends', $service->slugify('Černý & Friends'));
        $this->assertSame('master-i-margarita', $service->slugify('Мастер и Маргарита'));
    }

    private function service(): ShowService
    {
        return new ShowService($this->createMock(EntityManager::class));
    }

    private function show(
        ?string $summary = 'An ambitious drama about people who talk slowly.',
        ?string $imageMedium = 'https://static.tvmaze.com/medium.jpg',
        ?string $imageOriginal = 'https://static.tvmaze.com/original.jpg',
        int $episodes = ShowService::MIN_INDEXABLE_EPISODES
    ): Show {
        $episodeMocks = [];
        for ($i = 0; $i < $episodes; $i++) {
            $episodeMocks[] = $this->createMock(Episode::class);
        }

        $show = $this->createMock(Show::class);
        $show->method('getSummary')->willReturn($summary);
        $show->method('getImageMedium')->willReturn($imageMedium);
        $show->method('getImageOriginal')->willReturn($imageOriginal);
        $show->method('getEpisodes')->willReturn(new ArrayCollection($episodeMocks));

        return $show;
    }
}
