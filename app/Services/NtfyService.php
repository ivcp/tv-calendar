<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use GuzzleHttp\Client;
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


    public function createUser(string $username, string $password): void
    {

        $users = $this->getAllUsers();
        foreach ($users as $user) {
            if ($username === $user['username']) {
                throw new RuntimeException("Username taken");
            }
        }

        $response = $this->client->put($this->url, [
        'headers' => $this->headers,
        'body' => json_encode([
            'username' => $username,
            'password' => $password
        ])]);

        if ($response->getStatusCode() !== 200) {
            $message = $response->getReasonPhrase();
            throw new RuntimeException("Response failed with message: $message");
        }

        //TODO:
        //create user permission for topic on ntfy
        //custom exception


    }

    public function getAllUsers(): array
    {
        $response = $this->client->get($this->url, ['headers' => $this->headers]);
        if ($response->getStatusCode() !== 200) {
            $message = $response->getReasonPhrase();
            throw new RuntimeException("Response failed with message: $message");
        }

        return json_decode((string)$response->getBody(), true);
    }



}
