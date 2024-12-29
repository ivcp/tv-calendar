<?php

declare(strict_types=1);

namespace App\Services\Traits;

use Doctrine\DBAL\ArrayParameterType;
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
        ParameterType|ArrayParameterType $type
    ): void {
        $params[$it->key()] = $value;
        if ($value === 0) {
            $types[$it->key()] = $type;
        } else {
            $types[$it->key()] = $value ? $type : ParameterType::NULL;
        }
        $it->next();
    }
}
