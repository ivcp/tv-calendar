<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Exception\BadRequestException;
use Valitron\Validator;

class SearchShowRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {

        $v = new Validator($data);

        $v->rules([
            'required' => [
                ['query']
            ],
            'lengthMin' => [
                ['query', 1]
            ],
            'optional' => [
                ['page']
            ],
            'numeric' => [
                ['page']
            ],
            'min' => [
                ['page', 1]
            ],
        ]);

        if (! $v->validate()) {
            throw new BadRequestException();
        }

        return $data;
    }
}
