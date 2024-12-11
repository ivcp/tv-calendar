<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping\Column;

trait HasSummary
{
    #[Column(type: 'text', nullable: true)]
    private string $summary;
}
