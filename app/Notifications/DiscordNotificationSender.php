<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\NotificationMessage;
use App\Exception\NotificationFailedException;
use GuzzleHttp\Client;

class DiscordNotificationSender implements NotificationSenderInterface
{

    public function __construct(
        private readonly Client $client
    ) {}


    public function send(NotificationMessage $content): void
    {
        $message = <<<MESSAGE
        $content->title
        $content->message 
        $content->showLink
        MESSAGE;

        $response = $this->client->post($content->address, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['content' => $message])
        ]);

        if ($response->getStatusCode() !== 204) {
            throw new NotificationFailedException('Failed sending discord webhook: ' . $response->getReasonPhrase());
        }
    }
}
