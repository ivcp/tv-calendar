<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Config;
use App\Contracts\CachePurgeInterface;
use App\Services\CachePurgeService;
use App\Services\WebhookService;
use PHPUnit\Framework\TestCase;

class CachePurgeServiceTest extends TestCase
{
    public function testAfterUpdatePurgesTheCalendarDiscoverShowsAndSitemap(): void
    {
        $cache = $this->createMock(CachePurgeInterface::class);
        $cache->expects($this->once())
            ->method('purgePaths')
            ->with(['/'])
            ->willReturn(true);
        $cache->expects($this->once())
            ->method('purgePrefixes')
            ->with(['/discover', '/shows/', '/sitemap'])
            ->willReturn(true);

        $webhook = $this->createMock(WebhookService::class);
        $webhook->expects($this->never())->method('send');

        $this->assertTrue($this->service($cache, $webhook)->afterUpdate());
    }

    public function testAfterUpdateSendsAWebhookWhenEitherPurgeFails(): void
    {
        $cache = $this->createMock(CachePurgeInterface::class);
        $cache->method('purgePaths')->willReturn(true);
        $cache->method('purgePrefixes')->willReturn(false);

        $webhook = $this->createMock(WebhookService::class);
        $webhook->expects($this->once())
            ->method('send')
            ->with(
                'https://discord.test/webhook',
                $this->stringContains('Cache purge failed after the TVMaze update')
            );

        $this->assertFalse($this->service($cache, $webhook)->afterUpdate());
    }

    public function testAfterDeployPurgesEverythingForTheSiteHosts(): void
    {
        $cache = $this->createMock(CachePurgeInterface::class);
        $cache->expects($this->once())->method('purgeAll')->willReturn(true);
        $cache->expects($this->never())->method('purgePaths');
        $cache->expects($this->never())->method('purgePrefixes');

        $webhook = $this->createMock(WebhookService::class);
        $webhook->expects($this->never())->method('send');

        $this->assertTrue($this->service($cache, $webhook)->afterDeploy());
    }

    public function testAfterDeploySendsAWebhookWhenThePurgeFails(): void
    {
        $cache = $this->createMock(CachePurgeInterface::class);
        $cache->method('purgeAll')->willReturn(false);

        $webhook = $this->createMock(WebhookService::class);
        $webhook->expects($this->once())
            ->method('send')
            ->with(
                'https://discord.test/webhook',
                $this->stringContains('Cache purge failed after deploy')
            );

        $this->assertFalse($this->service($cache, $webhook)->afterDeploy());
    }

    private function service(CachePurgeInterface $cache, WebhookService $webhook): CachePurgeService
    {
        return new CachePurgeService(
            $cache,
            $webhook,
            new Config(['webhook_url' => 'https://discord.test/webhook'])
        );
    }
}
