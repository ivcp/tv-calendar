<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Exception\BadRequestException;
use Valitron\Validator;

class ScheduleRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {

        $v = new Validator($data);

        $v->rules([
            'required' => [
                ['tz'], ['schedule']
            ],
            'optional' => [
                ['shows']
            ],
            'array' =>
            [
                ['shows']
            ],
            'in' => [
                ['schedule', ['user', 'popular']],
            ]
        ]);

        if (! $v->validate()) {
            throw new BadRequestException();
        }

        return $data;
    }
}
