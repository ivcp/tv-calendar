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

    /**
     * Get the value of imageMedium
     */
    public function getImageMedium(): string
    {
        return $this->imageMedium;
    }

    /**
     * Set the value of imageMedium
     *
     * @return  self
     */
    public function setImageMedium(?string $imageMedium): self
    {
        if ($imageMedium) {
            $this->imageMedium = $imageMedium;
        }

        return $this;
    }

    /**
     * Get the value of imageOriginal
     */
    public function getImageOriginal(): string
    {
        return $this->imageOriginal;
    }

    /**
     * Set the value of imageOriginal
     *
     * @return  self
     */
    public function setImageOriginal(?string $imageOriginal): self
    {
        if ($imageOriginal) {
            $this->imageOriginal = $imageOriginal;
        }

        return $this;
    }
}
