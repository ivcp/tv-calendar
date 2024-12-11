<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasImages;
use App\Entity\Traits\HasName;
use App\Entity\Traits\HasRuntime;
use App\Entity\Traits\HasSummary;
use App\Entity\Traits\HasTimestamps;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table('episodes')]
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
    private Show $shows;

    public function __construct()
    {
        $this->shows = new ArrayCollection();
    }
}
