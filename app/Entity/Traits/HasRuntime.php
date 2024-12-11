<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping\Column;

trait HasRuntime
{

    #[Column(type: 'smallint', options: ['unsigned' => true], nullable: true)]
    private int $runtime;
}
