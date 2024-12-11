<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping\Column;

trait HasSummary
{
    #[Column(type: 'text', nullable: true)]
    private string $summary;

    /**
     * Get the value of summary
     */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * Set the value of summary
     *
     * @return  self
     */
    public function setSummary($summary): self
    {
        $this->summary = $summary;

        return $this;
    }
}
