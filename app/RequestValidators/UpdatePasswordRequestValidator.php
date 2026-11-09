<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Exception\ValidationException;
use Valitron\Validator;

class UpdatePasswordRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {
        $v = new Validator($data);

        $v->rule('required', ['password', 'new_password']);
        $v->rule('lengthMin', 'password', 8);
        $v->rule('lengthMin', 'new_password', 8);
        $v->rule(
            fn ($field, $value) => is_string($value) && strlen($value) <= 72,
            'new_password'
        )->message('Password must not exceed 72 bytes.');

        if (! $v->validate()) {
            throw new ValidationException($v->errors());
        }

        return $data;
    }
}
