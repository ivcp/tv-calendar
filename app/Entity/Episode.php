<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasImages;
use App\Entity\Traits\HasName;
use App\Entity\Traits\HasRuntime;
use App\Entity\Traits\HasSummary;
use App\Entity\Traits\HasTimestamps;
use DateTime;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table('episodes')]
#[HasLifecycleCallbacks]
class Episode
{
    use HasTimestamps;
    use HasSummary;
    use HasName;
    use HasImages;
    use HasRuntime;

    #[Id, Column(options: ['unsigned' => true]), GeneratedValue]
    private int $id;
    #[Column(name: 'tv_maze_episode_id', options: ['unsigned' => true])]
    private int $tvMazeEpisodeId;
    #[Column(type: 'smallint', options: ['unsigned' => true], nullable: true)]
    private int $season;
    #[Column(type: 'smallint', options: ['unsigned' => true], nullable: true)]
    private int $number;
    #[Column(type: 'datetimetz')]
    private DateTime $airstamp;
    #[Column(type: 'string', nullable: true)]
    private string $type;



    #[ManyToOne(inversedBy: 'episode', targetEntity: Show::class)]
    private Show $show;


    /**
     * Get the value of id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the value of tvMazeEpisodeId
     */
    public function getTvMazeEpisodeId(): int
    {
        return $this->tvMazeEpisodeId;
    }

    /**
     * Set the value of tvMazeEpisodeId
     *
     * @return  self
     */
    public function setTvMazeEpisodeId($tvMazeEpisodeId): Episode
    {
        $this->tvMazeEpisodeId = $tvMazeEpisodeId;

        return $this;
    }

    /**
     * Get the value of season
     */
    public function getSeason(): int
    {
        return $this->season;
    }

    /**
     * Set the value of season
     *
     * @return  self
     */
    public function setSeason($season): Episode
    {
        $this->season = $season;

        return $this;
    }

    /**
     * Get the value of number
     */
    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * Set the value of number
     *
     * @return  self
     */
    public function setNumber($number): Episode
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Get the value of airstamp
     */
    public function getAirstamp(): DateTime
    {
        return $this->airstamp;
    }

    /**
     * Set the value of airstamp
     *
     * @return  self
     */
    public function setAirstamp($airstamp): Episode
    {
        $this->airstamp = $airstamp;

        return $this;
    }

    /**
     * Get the value of type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the value of type
     *
     * @return  self
     */
    public function setType($type): Episode
    {
        $this->type = $type;

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
     * Set show
     *
     * @return  self
     */
    public function setShow($show): Episode
    {
        $this->show = $show;

        return $this;
    }
}
