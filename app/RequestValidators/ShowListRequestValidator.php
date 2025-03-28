<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Enum\Genres;
use App\Enum\ShowListSort;
use App\Exception\BadRequestException;
use Valitron\Validator;

class ShowListRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {

        $v = new Validator($data);

        $v->rules([
            'optional' => [
                ['page', 'sort', 'genre', 'shows']
            ],
            'numeric' => [
                ['page']
            ],
            'array' =>
            [
                ['shows']
            ],
            'min' => [
                ['page', 1]
            ],
            'in' => [
                ['sort', array_column(ShowListSort::cases(), 'value')],
                ['genre', array_column(Genres::cases(), 'value')]
            ]
        ]);

        if (! $v->validate()) {
            throw new BadRequestException();
        }

        return $data;
    }
}
