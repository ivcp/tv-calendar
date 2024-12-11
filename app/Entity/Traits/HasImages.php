<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping\Column;

trait HasImages
{
    #[Column(type: 'text', name: 'image_medium', nullable: true)]
    private string $imageMedium;
    #[Column(type: 'text', name: 'image_original', nullable: true)]
    private string $imageOriginal;
}
