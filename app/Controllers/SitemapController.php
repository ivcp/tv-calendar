<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Services\ShowService;
use DateTimeInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class SitemapController
{
    private const STATIC_PATHS = [
        '/',
        '/discover',
        '/about',
        '/get-notified',
        '/watch-while-traveling',
    ];

    /**
     * Sitemaps are capped at 50k URLs. Stay well below that; if this is ever
     * reached, split the sitemap into an index with per-type shards.
     */
    private const MAX_URLS_PER_SITEMAP = 45000;

    public function __construct(
        private readonly ShowService $showService,
        private readonly Config $config,
    ) {}

    public function generate(Request $request, Response $response): Response
    {
        $baseUrl = rtrim((string) $this->config->get('app_url'), '/');

        $shows = $this->showService->getSitemapShows();

        if (count($shows) > self::MAX_URLS_PER_SITEMAP) {
            error_log(sprintf(
                'Sitemap reached the %d URL limit and was truncated; split it into a sitemap index.',
                self::MAX_URLS_PER_SITEMAP
            ));
            $shows = array_slice($shows, 0, self::MAX_URLS_PER_SITEMAP);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach (self::STATIC_PATHS as $path) {
            $xml .= $this->url($baseUrl . $path);
        }

        foreach ($shows as $show) {
            $showUrl = $baseUrl . '/shows/' . $show['id'] . '-' . $this->showService->slugify($show['name']);
            $xml .= $this->url($showUrl, $show['updatedAt']);
        }

        $xml .= '</urlset>';

        $response->getBody()->write($xml);

        return $response
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=0, s-maxage=86400');
    }

    private function url(string $loc, ?DateTimeInterface $lastmod = null): string
    {
        $url = '<url><loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc>';

        if ($lastmod !== null) {
            $url .= '<lastmod>' . $lastmod->format('Y-m-d') . '</lastmod>';
        }

        return $url . '</url>';
    }
}
