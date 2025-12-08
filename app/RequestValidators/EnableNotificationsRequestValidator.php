<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Exception\ValidationException;
use Valitron\Validator;

class EnableNotificationsRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {
        $v = new Validator($data);

        $v->rule('required', ['notificationsPassword', 'confirmNotificationsPassword']);
        $v->rule('equals', 'notificationsPassword', 'confirmNotificationsPassword')
                ->message("Password and Confirm password must match");
        $v->rule('lengthMin', 'notificationsPassword', 8)
                ->message("Password must be at least 8 characters long");
        $v->rule('lengthMin', 'confirmNotificationsPassword', 8)
                ->message("Confirm password must be at least 8 characters long");


        if (! $v->validate()) {
            throw new ValidationException($v->errors());
        }

        return $data;
    }
}
