<?php

declare(strict_types=1);

namespace App\Services\Traits;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Iterator;
use SplFixedArray;

trait ParamsTypesCases
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

    private function setCase(
        array &$cases,
        string $name,
        int $id,
        ParameterType $type
    ): void {
        if ($type === ParameterType::INTEGER) {
            $cases[$name][] = "WHEN id = $id THEN cast(? as int)";
        } elseif ($name === 'airstamp') {
            $cases[$name][] = "WHEN id = $id THEN cast(? as timestamptz)";
        } else {
            $cases[$name][] = "WHEN id = $id THEN ?";
        }
    }

    private function setCaseAndParams(
        string $name,
        ParameterType $type,
        SplFixedArray $params,
        SplFixedArray $types,
        Iterator $it,
        array $entities,
        array &$cases
    ): void {
        foreach ($entities as $id => $entity) {
            $value = $entity->$name;
            if ($name === 'genres') {
                $value = $entity->genres ? implode(',', $entity->genres) : null;
            }
            if ($name === 'airstamp') {
                $value =  $entity->airstamp ? $entity->airstamp->format(DATE_ATOM) : null;
            }
            $this->setParameterAndType($params, $types, $it, $value, $type);

            $this->setCase($cases, $name, $id, $type);
        }
    }
}
