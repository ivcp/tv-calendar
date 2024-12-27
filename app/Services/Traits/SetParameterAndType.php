<?php

declare(strict_types=1);

namespace App\Services\Traits;

use Doctrine\DBAL\ParameterType;
use Iterator;
use SplFixedArray;

trait SetParameterAndType
{

    private function setParameterAndType(
        SplFixedArray $params,
        SplFixedArray $types,
        Iterator $it,
        mixed $value,
        ParameterType $type
    ): void {
        $params[$it->key()] = $value;
        $types[$it->key()] = $value ? $type : ParameterType::NULL;
        $it->next();
    }
}
