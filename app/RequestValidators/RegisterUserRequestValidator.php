<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Config;
use App\Contracts\RequestValidatorInterface;
use App\Entity\User;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Client;
use Valitron\Validator;

class RegisterUserRequestValidator implements RequestValidatorInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Client $client,
        private readonly Config $config
    ) {
    }

    public function validate(array $data): array
    {
        $v = new Validator($data);
        $v->rule('required', ['email', 'password', 'confirm_password']);
        $v->rule('required', 'terms_check')->message("You must agree to the Terms of use.");
        $v->rule('optional', 'shows');
        $v->rule('array', 'shows');
        $v->rule('email', 'email');
        $v->rule('equals', 'password', 'confirm_password')
            ->message("Password and Confirm password must match.");
        $v->rule(
            fn ($field, $value, $params, $fields) =>
                !$this->entityManager->getRepository(User::class)->count(['email' => $value]),
            'email'
        )->message("Account with that email already exists.");
        $v->rule('lengthMin', 'password', 8);
        $v->rule('lengthMin', 'confirm_password', 8);

        if (! $v->validate()) {
            throw new ValidationException($v->errors());
        }

        if (!isset($data['cf-turnstile-response']) || trim($data['cf-turnstile-response']) === '') {
            throw new ValidationException(['turnstile' => ['Failed bot verification']]);
        }

        if (
            !$this->validateTurnstile(
                $data['cf-turnstile-response'],
                $this->config->get('turnstile.secret_key')
            )
        ) {
            throw new ValidationException(['turnstile' => ['Failed bot verification']]);
        }

        return $data;
    }

    private function validateTurnstile($token, $secret, $remoteip = null): bool
    {
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

        $data = [
            'secret' => $secret,
            'response' => $token
        ];

        if ($remoteip) {
            $data['remoteip'] = $remoteip;
        }


        $options = [
                'headers' =>
                [
                    "Content-type" => "application/json",
                ],
                'body' => json_encode($data)
        ];

        $response = $this->client->post($url, $options);

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $body = json_decode((string)$response->getBody());

        if (!$body->success) {
            return false;
        }

        return true;
    }
}
