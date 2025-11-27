<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contracts\UserInterface;
use App\Entity\Traits\HasTimestamps;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table('users')]
#[HasLifecycleCallbacks]
class User implements UserInterface
{
    use HasTimestamps;

    #[Id, Column(options: ['unsigned' => true]), GeneratedValue]
    private int $id;

    #[Column(type:'string', unique:true)]
    private string $email;

    #[Column(type:'string', nullable: true)]
    private ?string $password;

    #[Column(name: 'verified_at', nullable: true)]
    private ?DateTime $verifiedAt;

    #[Column(name: 'start_of_week_sunday', options: ['default' => false])]
    private bool $startOfWeekSunday;

    #[Column(name:'ntfy_topic', type:'string', length: 10, nullable: true, unique: true)]
    private ?string $ntfyTopic;

    #[OneToMany(targetEntity: UserShows::class, mappedBy:'user', cascade:['persist'])]
    private Collection $userShows;


    public function __construct()
    {
        $this->userShows = new ArrayCollection();
    }



    /**
     * Get userShows
     */
    public function getShows(): Collection
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

    /**
     * Get the value of email
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set the value of email
     *
     * @return  self
     */
    public function setEmail(string $email): User
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get the value of password
     */
    public function getPassword(): ?string
    {
        return $this->password ?: null;
    }

    /**
     * Set the value of password
     *
     * @return  self
     */
    public function setPassword(?string $password): User
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get the value of id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the value of verifiedAt
     */
    public function getVerifiedAt(): ?DateTime
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?DateTime $date): self
    {
        $this->verifiedAt = $date;
        return $this;
    }

    /**
     * Get the value of startOfWeekSunday
     */
    public function getStartOfWeekSunday(): bool
    {
        return $this->startOfWeekSunday;
    }

    /**
     * Set the value of startOfWeekSunday
     *
     * @return  self
     */
    public function setStartOfWeekSunday(bool $startOfWeekSunday): self
    {
        $this->startOfWeekSunday = $startOfWeekSunday;

        return $this;
    }

    /**
     * Get the value of ntfyTopic
     */
    public function getNtfyTopic(): ?string
    {
        return $this->ntfyTopic;
    }

    /**
     * Set the value of ntfyTopic
     *
     * @return  self
     */
    public function setNtfyTopic(?string $ntfyTopic): self
    {
        $this->ntfyTopic = $ntfyTopic;

        return $this;
    }
}
