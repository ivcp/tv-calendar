<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;


class WebhookService
{
    public function __construct(
        private readonly Client $client
    ) {}


    public function send(
        string $url,
        string $content
    ): void {
        $response = $this->client->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['content' => $content])
        ]);

        if ($response->getStatusCode() !== 204) {
            error_log('Error sending webhook: ' . $response->getReasonPhrase());
        }
    }
}
