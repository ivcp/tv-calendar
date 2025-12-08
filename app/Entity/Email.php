<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasTimestamps;
use DateTime;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'emails')]
#[HasLifecycleCallbacks]
class Email
{
    use HasTimestamps;

    #[Id, Column(options: ['unsigned' => true]), GeneratedValue]
    private int $id;

    #[Column]
    private string $to;

    #[Column]
    private string $subject;

    #[Column(name: 'html_template')]
    private string $htmlTemplate;

    #[Column(name: 'activation_link', type: 'text', nullable: true, options: ['default' => null])]
    private ?string $activationLink = null;

    #[Column(name: 'expiration_date', nullable: true, options: ['default' => null])]
    private ?DateTime $expirationDate = null;

    #[Column(name: 'reset_link', type: 'text', nullable: true, options: ['default' => null])]
    private ?string $resetLink = null;

    /**
     * Get the value of id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the value of to
     */
    public function getTo(): string
    {
        return $this->to;
    }

    /**
     * Set the value of to
     *
     * @return  self
     */
    public function setTo(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Get the value of htmlTemplate
     */
    public function getHtmlTemplate(): string
    {
        return $this->htmlTemplate;
    }

    /**
     * Set the value of htmlTemplate
     *
     * @return  self
     */
    public function setHtmlTemplate(string $htmlTemplate): self
    {
        $this->htmlTemplate = $htmlTemplate;

        return $this;
    }

    /**
     * Get the value of activationLink
     */
    public function getActivationLink(): ?string
    {
        return $this->activationLink;
    }

    /**
     * Set the value of activationLink
     *
     * @return  self
     */
    public function setActivationLink(?string $activationLink): self
    {
        $this->activationLink = $activationLink;

        return $this;
    }

    /**
     * Get the value of resetLink
     */
    public function getResetLink(): ?string
    {
        return $this->resetLink;
    }

    /**
     * Set the value of resetLink
     *
     * @return  self
     */
    public function setResetLink(?string $resetLink): self
    {
        $this->resetLink = $resetLink;

        return $this;
    }

    /**
     * Get the value of expirationDate
     */
    public function getExpirationDate(): DateTime
    {
        return $this->expirationDate;
    }

    /**
     * Set the value of expirationDate
     *
     * @return  self
     */
    public function setExpirationDate(?DateTime $expirationDate): self
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }

    /**
     * Get the value of subject
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Set the value of subject
     *
     * @return  self
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }
}
