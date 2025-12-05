<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Exception\ValidationException;
use Valitron\Validator;

class UpdateShowRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {

        $v = new Validator($data);

        $v->rules([
            'required' => [
                ['notificationsEnabled']
            ],
            'boolean' => [
                ['notificationsEnabled']
            ]
        ]);

        if (! $v->validate()) {
            throw new ValidationException($v->errors());
        }

        return $data;
    }
}
