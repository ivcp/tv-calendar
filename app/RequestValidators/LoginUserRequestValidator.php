<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Exception\ValidationException;
use Valitron\Validator;

class LoginUserRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {
        $data['email'] = is_string($data['email'] ?? null)
            ? mb_strtolower(trim($data['email']), 'UTF-8')
            : '';

        // The form sends a hidden "0" plus a checked "1", so a missing field
        // and a malformed one both mean "do not remember this browser".
        $data['remember'] = in_array($data['remember'] ?? null, ['1', 1, true], true);

        $v = new Validator($data);

        $v->rule('required', ['email', 'password']);
        $v->rule('email', 'email');
        $v->rule('optional', 'shows');
        $v->rule('array', 'shows');

        if (! $v->validate()) {
            throw new ValidationException($v->errors());
        }

        return $data;
    }
}
