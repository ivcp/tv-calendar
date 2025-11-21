<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Entity\User;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManager;
use Valitron\Validator;

class RegisterUserRequestValidator implements RequestValidatorInterface
{
    public function __construct(private readonly EntityManager $entityManager)
    {
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

        return $data;
    }
}
