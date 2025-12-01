<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasTimestamps;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table('users_shows')]
#[HasLifecycleCallbacks]
class UserShows
{
    use HasTimestamps;

    #[Column(name:'notifications_enabled', options:['default' => true])]
    private bool $notificationsEnabled = true;

    #[Id, ManyToOne(targetEntity:User::class), JoinColumn(nullable:false, onDelete:'CASCADE')]
    private User $user;

    #[Id, ManyToOne(targetEntity:Show::class), JoinColumn(nullable:false, onDelete:'CASCADE')]
    private Show $show;


    /**
     * Set user
     *
     * @return  self
     */
    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get show
     */
    public function getShow(): Show
    {
        return $this->show;
    }

    /**
     * Set  show
     *
     * @return  self
     */
    public function setShow(Show $show): self
    {
        $this->show = $show;

        return $this;
    }

    /**
     * Get the value of notificationsEnabled
     */
    public function getNotificationsEnabled(): bool
    {
        return $this->notificationsEnabled;
    }

    /**
     * Set the value of notificationsEnabled
     *
     * @return  self
     */
    public function setNotificationsEnabled(bool $notificationsEnabled): self
    {
        $this->notificationsEnabled = $notificationsEnabled;

        return $this;
    }
}
