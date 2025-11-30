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
    private string $adminToken;
    private array $headers;

    public function __construct(
        public readonly Client $client,
        public readonly Config $config
    ) {
        $this->url = $config->get('ntfy.base_url') . '/v1/users';
        $this->adminToken = $this->config->get('ntfy.admin_token');
        $this->headers = ['Authorization' => "Bearer $this->adminToken"];
    }


    public function generateTopic(): string
    {
        return bin2hex(random_bytes(5));
    }

    public function getAllUsers(): array
    {
        $response = $this->client->get($this->url, ['headers' => $this->headers]);
        $this->checkStatus($response);

        return json_decode((string)$response->getBody(), true);
    }


    public function createUser(string $username, string $password, string $topic): void
    {

        $response = $this->client->post($this->url, [
        'headers' => $this->headers,
        'body' => json_encode([
            'username' => $username,
            'password' => $password
        ])]);
        $this->checkStatus($response);


        $response = $this->client->put($this->url . '/access', [
        'headers' => $this->headers,
        'body' => json_encode([
           'username' => $username,
           'topic' => $topic,
           'permission' => 'read-only'
        ])]);
        $this->checkStatus($response);

    }

    public function deleteUser(string $username): void
    {

        $response = $this->client->delete($this->url, [
        'headers' => $this->headers,
        'body' => json_encode([
          'username' => $username,
        ])]);
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
