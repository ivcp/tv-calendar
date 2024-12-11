<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\HasImages;
use App\Entity\Traits\HasName;
use App\Entity\Traits\HasRuntime;
use App\Entity\Traits\HasSummary;
use App\Entity\Traits\HasTimestamps;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table('shows')]
#[HasLifecycleCallbacks]
class Show
{
    use HasTimestamps;
    use HasSummary;
    use HasName;
    use HasImages;
    use HasRuntime;

    #[Id, Column(options: ['unsigned' => true]), GeneratedValue]
    private int $id;
    #[Column(name: 'tv_maze_id', options: ['unsigned' => true])]
    private int $tvMazeId;
    #[Column(name: 'imdb_id', type: 'string')]
    private string $imdbId;
    #[Column(type: 'simple_array')]
    private array $genres;
    #[Column(type: 'string')]
    private string $status;
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


    #[OneToMany(mappedBy: 'show', targetEntity: Episode::class)]
    private Collection $episodes;

    public function __construct()
    {
        $this->episodes = new ArrayCollection();
    }

    /**
     * Get the value of id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the value of tvMazeId
     */
    public function getTvMazeId(): int
    {
        return $this->tvMazeId;
    }

    /**
     * Set the value of tvMazeId
     *
     * @return  self
     */
    public function setTvMazeId($tvMazeId): Show
    {
        $this->tvMazeId = $tvMazeId;

        return $this;
    }

    /**
     * Get the value of imdbId
     */
    public function getImdbId(): string
    {
        return $this->imdbId;
    }

    /**
     * Set the value of imdbId
     *
     * @return  self
     */

    public function setImdbId($imdbId): Show
    {
        $this->imdbId = $imdbId;

        return $this;
    }

    /**
     * Get the value of genres
     */
    public function getGenres(): array
    {
        return $this->genres;
    }

    /**
     * Set the value of genres
     *
     * @return  self
     */
    public function setGenres($genres): Show
    {
        $this->genres = $genres;

        return $this;
    }

    /**
     * Get the value of status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set the value of status
     *
     * @return  self
     */
    public function setStatus($status): Show
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get the value of premiered
     */
    public function getPremiered(): string
    {
        return $this->premiered;
    }

    /**
     * Set the value of premiered
     *
     * @return  self
     */
    public function setPremiered($premiered): Show
    {
        $this->premiered = $premiered;

        return $this;
    }

    /**
     * Get the value of ended
     */
    public function getEnded(): string
    {
        return $this->ended;
    }

    /**
     * Set the value of ended
     *
     * @return  self
     */
    public function setEnded($ended): Show
    {
        $this->ended = $ended;

        return $this;
    }

    /**
     * Get the value of officialSite
     */
    public function getOfficialSite(): string
    {
        return $this->officialSite;
    }

    /**
     * Set the value of officialSite
     *
     * @return  self
     */
    public function setOfficialSite($officialSite): Show
    {
        $this->officialSite = $officialSite;

        return $this;
    }

    /**
     * Get the value of weight
     */
    public function getWeight(): int
    {
        return $this->weight;
    }

    /**
     * Set the value of weight
     *
     * @return  self
     */
    public function setWeight($weight): Show
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * Get the value of networkName
     */
    public function getNetworkName(): string
    {
        return $this->networkName;
    }

    /**
     * Set the value of networkName
     *
     * @return  self
     */
    public function setNetworkName($networkName): Show
    {
        $this->networkName = $networkName;

        return $this;
    }

    /**
     * Get the value of networkCountry
     */
    public function getNetworkCountry(): string
    {
        return $this->networkCountry;
    }

    /**
     * Set the value of networkCountry
     *
     * @return  self
     */
    public function setNetworkCountry($networkCountry): Show
    {
        $this->networkCountry = $networkCountry;

        return $this;
    }

    /**
     * Get the value of webChannelName
     */
    public function getWebChannelName(): string
    {
        return $this->webChannelName;
    }

    /**
     * Set the value of webChannelName
     *
     * @return  self
     */
    public function setWebChannelName($webChannelName): Show
    {
        $this->webChannelName = $webChannelName;

        return $this;
    }

    /**
     * Get episodes
     */
    public function getEpisodes(): ArrayCollection|Collection
    {
        return $this->episodes;
    }

    /**
     * Set episodes
     *
     * @return  self
     */
    public function setEpisodes($episodes): Show
    {
        $this->episodes = $episodes;

        return $this;
    }
}
