<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ShowService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class SitemapController
{
    public function __construct(
        private readonly ShowService $showService,
    ) {}

    public function generate(Request $request, Response $response): Response
    {
        $shows = $this->showService->getSitemapShows();

        $baseUrl = 'https://www.tvshowcalendar.com';

        // 4. Start building the XML string framework
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // 5. Inject your static core pages manually at the top
        // Homepage
        $xml .= '<url>';
        $xml .= '<loc>' . $baseUrl . '/</loc>';
        $xml .= '<changefreq>daily</changefreq>';
        $xml .= '<priority>1.0</priority>';
        $xml .= '</url>';

        // Discover Page
        $xml .= '<url>';
        $xml .= '<loc>' . $baseUrl . '/discover</loc>';
        $xml .= '<changefreq>daily</changefreq>';
        $xml .= '<priority>0.8</priority>';
        $xml .= '</url>';

        // About Page
        $xml .= '<url>';
        $xml .= '<loc>' . $baseUrl . '/about</loc>';
        $xml .= '<changefreq>daily</changefreq>';
        $xml .= '<priority>0.8</priority>';
        $xml .= '</url>';

        // 6. Loop through the database results to append the 500 hybrid show URLs
        foreach ($shows as $show) {
            // Use the Cyrillic-safe slug utility function built previously
            $slug = $this->showService->slugify($show['name']);
            $showUrl = $baseUrl . '/shows/' . $show['id'] . '-' . $slug;

            // Ensure the date format follows the standard W3C pattern (YYYY-MM-DD)
            $lastmod = $show['updatedAt']->format('Y-m-d');

            $xml .= '<url>';
            // htmlspecialchars handles any edge-case XML characters safely
            $xml .= '<loc>' . htmlspecialchars($showUrl, ENT_XML1, 'UTF-8') . '</loc>';
            $xml .= '<lastmod>' . $lastmod . '</lastmod>';
            $xml .= '<changefreq>weekly</changefreq>';
            $xml .= '<priority>0.6</priority>';
            $xml .= '</url>';
        }

        // Close the schema tag structure
        $xml .= '</urlset>';

        // 7. Stream the raw text into the response body
        $response->getBody()->write($xml);

        return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }
}
