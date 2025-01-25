<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Exception\BadRequestException;
use App\Exception\ValidationException;
use Valitron\Validator;

class DeleteShowRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {

        $v = new Validator($data);

        $v->rules([
            'required' => [
                ['id']
            ],
            'integer' => [
                ['id']
            ],
            'min' => [
                ['id', 1]
            ]
        ]);

        if (! $v->validate()) {
            throw new ValidationException($v->errors());
        }

        return $data;
    }

}
