<?php

declare(strict_types=1);

namespace App\Entity;

use App\DataObjects\NotificationMessage;
use App\Entity\Traits\HasTimestamps;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'notifications')]
#[HasLifecycleCallbacks]
class Notification
{
    use HasTimestamps;

    #[Id, Column(options: ['unsigned' => true]), GeneratedValue]
    private int $id;

    #[Column(type: Types::JSON)]
    private NotificationMessage $content;

    #[Column(name: 'scheduled_time')]
    private DateTime $scheduledTime;

    #[Column(options: ['default' => false])]
    private bool $processed;

    //add type to support more types like email

    /**
     * Get the value of id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the value of content
     */
    public function getContent(): NotificationMessage
    {
        return $this->content;
    }

    /**
     * Set the value of content
     *
     * @return  self
     */
    public function setContent(NotificationMessage $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get the value of scheduledTime
     */
    public function getScheduledTime(): DateTime
    {
        return $this->scheduledTime;
    }

    /**
     * Set the value of scheduledTime
     *
     * @return  self
     */
    public function setScheduledTime(DateTime $scheduledTime): self
    {
        $this->scheduledTime = $scheduledTime;

        return $this;
    }

    /**
     * Get the value of status
     */
    public function getProcessedStatus(): bool
    {
        return $this->processed;
    }

    /**
     * Set the value of status
     *
     * @return  self
     */
    public function setProcessedStatus(bool $status): self
    {
        $this->processed = $status;

        return $this;
    }
}
