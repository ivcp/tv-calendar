<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasTimestamps;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table('users')]
#[HasLifecycleCallbacks]
class User
{
    use HasTimestamps;

    #[Id, Column(options: ['unsigned' => true]), GeneratedValue]
    private int $id;

    #[Column(type:'string')]
    private string $name;

    #[Column(type:'string')]
    private string $email;

    #[Column(type:'string')]
    private string $password;


    #[OneToMany(targetEntity: UserShows::class, mappedBy:'user', cascade:['persist'])]
    private Collection $userShows;


    public function __construct()
    {
        $this->userShows = new ArrayCollection();
    }



    /**
     * Get userShows
     */
    public function getUserShows(): Collection
    {
        return $this->userShows;
    }

    /**
     * Set userShows
     *
     * @return  self
     */
    public function addShow(Show $show): User
    {
        $userShow = new UserShows();
        $userShow->setUser($this);
        $userShow->setShow($show);
        $this->userShows->add($userShow);

        return $this;
    }
}