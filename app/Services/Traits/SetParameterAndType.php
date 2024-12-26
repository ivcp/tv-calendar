<?php

declare(strict_types=1);

namespace App\Services\Traits;

use Doctrine\DBAL\ParameterType;

trait SetParameterAndType
{

    private function setParameterAndType(
        array &$params,
        array &$types,
        string $name,
        int $i,
        mixed $value,
        ParameterType $type
    ): void {
        $i++;
        $params["$name$i"] = $value;
        $types["$name$i"] = $value ? $type : ParameterType::NULL;
    }
}
