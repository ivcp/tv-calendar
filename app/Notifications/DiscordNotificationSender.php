<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Config;
use App\DataObjects\NotificationMessage;
use App\Exception\NotificationFailedException;
use App\Services\DiscordWebhookUrlValidator;
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

        // Re-validate at send time: the stored URL may predate this check 
        if (! is_string($url) || ! DiscordWebhookUrlValidator::isValid($url)) {
            throw new NotificationFailedException('Refusing to send to an untrusted discord webhook url');
        }

        $message = <<<MESSAGE
        # $content->title\n
        $content->message 
        $content->showLink
        MESSAGE;

        $response = $this->client->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['content' => $message]),
            'allow_redirects' => false,
        ]);

        if ($response->getStatusCode() !== 204) {
            throw new NotificationFailedException('Failed sending discord webhook: ' . $response->getReasonPhrase());
        }
    }
}
