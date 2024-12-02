<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table('shows')]
class Show
{
    #[Id, Column(type: 'bigint', options: ['unsigned' => true]), GeneratedValue]
    private int $id;
    #[Column]
    private string $title;
}
