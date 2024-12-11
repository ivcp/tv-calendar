<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping\Column;

trait HasName
{
    #[Column(type: 'text')]
    private string $name;
}
