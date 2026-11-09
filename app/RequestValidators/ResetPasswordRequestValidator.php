<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Exception\ValidationException;
use Valitron\Validator;

class ResetPasswordRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {
        $v = new Validator($data);

        $v->rule('required', ['password', 'confirm_password']);
        $v->rule('equals', 'password', 'confirm_password')
        ->message("Password and Confirm password must match.");
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
}
