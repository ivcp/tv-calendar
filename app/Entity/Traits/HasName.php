<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping\Column;

trait HasName
{
    #[Column(type: 'text')]
    private string $name;

    /**
     * Get the value of name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @return  self
     */
    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }
}
