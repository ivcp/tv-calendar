<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasTimestamps;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table('shows')]
class Show
{

    use HasTimestamps;

    #[Id, Column(options: ['unsigned' => true]), GeneratedValue]
    private int $id;
    #[Column(name: 'tv_maze_id', options: ['unsigned' => true])]
    private int $tvMazeId;
    #[Column(name: 'imdb_id', type: 'string')]
    private string $imdbId;
    #[Column(type: 'text')]
    private string $name;
    #[Column(type: 'text', nullable: true)]
    private string $summary;
    #[Column(type: 'simple_array')]
    private array $genres;
    #[Column(type: 'string')]
    private string $status;
    #[Column(type: 'smallint', options: ['unsigned' => true], nullable: true)]
    private int $runtime;
    #[Column(type: 'string', nullable: true)]
    private string $premiered;
    #[Column(type: 'string', nullable: true)]
    private string $ended;
    #[Column(type: 'text', name: 'official_site', nullable: true)]
    private string $officialSite;
    #[Column(type: 'smallint', options: ['unsigned' => true, 'default' => 0])]
    private int $weight;
    #[Column(name: 'network_name', type: 'string', nullable: true)]
    private string $networkName;
    #[Column(name: 'network_country', type: 'string', nullable: true)]
    private string $networkCountry;
    #[Column(name: 'web_channel_name', type: 'string', nullable: true)]
    private string $webChannelName;
    #[Column(type: 'text', name: 'image_medium', nullable: true)]
    private string $imageMedium;
    #[Column(type: 'text', name: 'image_original', nullable: true)]
    private string $imageOriginal;

    #[OneToMany(mappedBy: 'show', targetEntity: Episode::class)]
    private Episode $episodes;

    public function __construct()
    {
        $this->episodes = new ArrayCollection();
    }
}
