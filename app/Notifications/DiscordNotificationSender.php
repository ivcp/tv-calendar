<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Config;
use App\DataObjects\NotificationMessage;
use App\Exception\NotificationFailedException;
use App\Services\UrlProtectionService;
use GuzzleHttp\Client;

class DiscordNotificationSender implements NotificationSenderInterface
{

    public function __construct(
        private readonly Client $client,
        private readonly Config $config
    ) {}


    public function send(NotificationMessage $content): void
    {

        $urlProtectionService = new UrlProtectionService($this->config->get('url_secret_key'));
        $url = $urlProtectionService->decrypt($content->address);

        $message = <<<MESSAGE
        # $content->title\n
        $content->message 
        $content->showLink
        MESSAGE;

        $response = $this->client->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['content' => $message])
        ]);

        if ($response->getStatusCode() !== 204) {
            throw new NotificationFailedException('Failed sending discord webhook: ' . $response->getReasonPhrase());
        }
    }
}
