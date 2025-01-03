<?php

declare(strict_types=1);

namespace App\DataFixtures\Fixtures;

use App\Entity\Episode;
use App\Entity\Show;
use DateTime;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class EpisodeDataLoader implements FixtureInterface
{
    public function load(ObjectManager $manager): void
    {

        $tvMazeId = 1;

        for ($i = 1; $i <= 50; $i++) {
            $episode = new Episode();

            $show = $manager->getRepository(Show::class)->findOneBy(['tvMazeId' => $tvMazeId]);
            if (!$show) {
                echo "failed to load episodes for show tvmaze id $i" . PHP_EOL;
                continue;
            }

            if ($i === $tvMazeId * 10) {
                $tvMazeId++;
            }

            $episode->setShow($show);
            $episode->setName("ep $i");
            $episode->setTvMazeEpisodeId($i);
            $episode->setAirstamp(new DateTime('now'));

            $manager->persist($show);
        }

        $manager->flush();
    }
}
