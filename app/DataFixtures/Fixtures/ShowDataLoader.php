<?php

declare(strict_types=1);

namespace App\DataFixtures\Fixtures;

use App\Entity\Show;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ShowDataLoader implements FixtureInterface
{
    public function load(ObjectManager $manager): void
    {

        for ($i = 1; $i <= 10; $i++) {
            $show = new Show();
            $show->setName('test show');
            $show->setSummary("summary $i");
            $show->setTvMazeId($i);
            $show->setImdbId('1');
            $show->setGenres(['a', 'b']);
            $show->setStatus('regular');
            $show->setPremiered('2000-01');
            $show->setEnded('2000-02');
            $show->setWeight(100);

            $manager->persist($show);
        }

        $manager->flush();
    }
}
