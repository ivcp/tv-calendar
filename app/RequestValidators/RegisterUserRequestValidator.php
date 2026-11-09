<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Config;
use App\Contracts\RequestValidatorInterface;
use App\Contracts\UserProviderServiceInterface;
use App\Exception\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Valitron\Validator;

class RegisterUserRequestValidator implements RequestValidatorInterface
{
    private const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly UserProviderServiceInterface $userProvider,
        private readonly Client $client,
        private readonly Config $config
    ) {
    }

    public function validate(array $data): array
    {
        $token = $data['cf-turnstile-response'] ?? null;

        if (! is_string($token) || trim($token) === '' || ! $this->validateTurnstile(trim($token))) {
            throw new ValidationException(['turnstile' => ['Failed bot verification']]);
        }

        $data['email'] = $this->normalizeEmail($data['email'] ?? null);

        $v = new Validator($data);
        $v->rule('required', ['email', 'password', 'confirm_password']);
        $v->rule('accepted', 'terms_check')->message("You must agree to the Terms of use.");
        $v->rule('optional', 'shows');
        $v->rule('array', 'shows');
        $v->rule('email', 'email');
        $v->rule('equals', 'password', 'confirm_password')
            ->message("Password and Confirm password must match.");
        $v->rule(
            fn ($field, $value, $params, $fields) => ! $this->userProvider->emailExists((string) $value),
            'email'
        )->message("Account with that email already exists.");
        $v->rule('lengthMin', 'password', 8);
        $v->rule('lengthMin', 'confirm_password', 8);
        $v->rule(
            fn ($field, $value) => is_string($value) && strlen($value) <= 72,
            'password'
        )->message('Password must not exceed 72 bytes.');

        if (! $v->validate()) {
            throw new ValidationException($v->errors());
        }

        return $data;
    }

    private function normalizeEmail(mixed $email): string
    {
        return is_string($email) ? mb_strtolower(trim($email), 'UTF-8') : '';
    }

    private function validateTurnstile(string $token): bool
    {
        $secret = $this->config->get('turnstile.secret_key');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        try {
            $response = $this->client->post(self::TURNSTILE_VERIFY_URL, [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => json_encode(
                    ['secret' => $secret, 'response' => $token],
                    JSON_THROW_ON_ERROR
                ),
            ]);
        } catch (GuzzleException | JsonException) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        try {
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return is_array($body) && ($body['success'] ?? false) === true;
    }
}
