<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;


class WebhookService
{
    public function __construct(
        private readonly Client $client
    ) {}


    public function send(
        ?string $url,
        string $content
    ): void {
        if (!$url) {
            return;
        }

        try {
            $response = $this->client->post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['content' => $content]),
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            error_log('Error sending webhook: ' . $e->getMessage());
            return;
        }

        if ($response->getStatusCode() !== 204) {
            error_log('Error sending webhook: ' . $response->getReasonPhrase());
        }
    }
}
