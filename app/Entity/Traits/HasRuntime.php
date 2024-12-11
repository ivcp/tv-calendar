<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping\Column;

trait HasRuntime
{

    #[Column(type: 'smallint', options: ['unsigned' => true], nullable: true)]
    private int $runtime;

    /**
     * Get the value of runtime
     */
    public function getRuntime(): int
    {
        return $this->runtime;
    }

    /**
     * Set the value of runtime
     *
     * @return  self
     */
    public function setRuntime(int $runtime): self
    {
        $this->runtime = $runtime;

        return $this;
    }
}
