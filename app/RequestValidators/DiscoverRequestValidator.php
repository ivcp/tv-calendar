<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Enum\DiscoverSort;
use App\Enum\Genres;
use App\Exception\BadRequestException;
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
                ['sort', array_column(DiscoverSort::cases(), 'value')],
                ['genre', array_column(Genres::cases(), 'value')]
            ]
        ]);

        if (! $v->validate()) {
            throw new BadRequestException();
        }

        return $data;
    }
}
