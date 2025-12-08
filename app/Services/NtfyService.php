<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class NtfyService
{
    private string $url;
    private string $usersUrl;
    private string $accessUrl;
    private string $adminToken;
    private array $headers;

    public function __construct(
        public readonly Client $client,
        public readonly Config $config
    ) {
        $this->url = $config->get('ntfy.base_url');
        $this->usersUrl = $this->url . '/v1/users';
        $this->accessUrl = $this->url . '/v1/users/access';
        $this->adminToken = $this->config->get('ntfy.admin_token');
        $this->headers = ['Authorization' => "Bearer $this->adminToken"];
    }


    public function generateTopic(): string
    {
        return bin2hex(random_bytes(5));
    }

    public function getAllUsers(): array
    {
        $response = $this->client->get($this->usersUrl, ['headers' => $this->headers]);
        $this->checkStatus($response);

        return json_decode((string)$response->getBody(), true);
    }


    public function createUser(string $username, string $password, string $topic): void
    {

        $response = $this->client->post($this->usersUrl, [
            'headers' => $this->headers,
            'body' => json_encode([
                'username' => $username,
                'password' => $password
            ])
        ]);
        $this->checkStatus($response);


        $response = $this->client->put($this->accessUrl, [
            'headers' => $this->headers,
            'body' => json_encode([
                'username' => $username,
                'topic' => $topic,
                'permission' => 'read-only'
            ])
        ]);
        $this->checkStatus($response);
    }

    public function deleteUser(string $username): void
    {

        $response = $this->client->delete($this->usersUrl, [
            'headers' => $this->headers,
            'body' => json_encode([
                'username' => $username,
            ])
        ]);
        $this->checkStatus($response);
    }

    public function sendNotification(
        string $topic,
        string $title,
        string $message,
        ?int $timestamp = null,
        ?string $showLink = null
    ): void {
        $url = $this->url . '/' . $topic;

        $headers = [
            'Title' => $title,
            'Tags' => 'tv',
            'Delay' => $timestamp,
            'Action' => "view, Go to show page, $showLink"
        ];
        if (!$timestamp && !$showLink) {
            unset($headers['Delay']);
            unset($headers['Action']);
        }

        $response = $this->client->post($url, [
            'headers' => array_merge(
                $this->headers,
                $headers
            ),
            'body' => $message
        ]);
        $this->checkStatus($response);
    }


    private function checkStatus(ResponseInterface $response): void
    {
        if ($response->getStatusCode() !== 200) {
            $message = $response->getReasonPhrase();
            throw new RuntimeException("Response failed with message: $message");
        }
    }
}
