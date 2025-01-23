<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Enum\Genres;
use App\Exception\BadRequestException;
use App\Exception\ValidationException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Valitron\Validator;

class DiscoverRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {

        $v = new Validator($data);

        $v->rules([
            'optional' => [
                ['page', 'sort', 'genre']
            ],
            'numeric' => [
                ['page']
            ],
            'min' => [
                ['page', 1]
            ],
            'in' => [
                ['sort', ['popular', 'new']],
                ['genre', array_column(Genres::cases(), 'value')]
            ]
        ]);

        if (! $v->validate()) {
            throw new BadRequestException();
        }

        return $data;
    }

}
