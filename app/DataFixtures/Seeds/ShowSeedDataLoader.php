<?php

declare(strict_types=1);

namespace App\DataFixtures\Seeds;

use App\Entity\Show;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use JsonMachine\Items;

class ShowSeedDataLoader implements FixtureInterface
{

    public function load(ObjectManager $manager): void
    {
        $shows = Items::fromFile(STORAGE_PATH . '/shows.json');

        $inserted = 0;
        $count = 1;
        $batchSize = 200;
        foreach ($shows as $key => $value) {

            $show = new Show();

            $show->setName($value->name);

            if ($value->summary) {
                $show->setSummary($value->summary);
            }

            $show->setTvMazeId($value->id);

            if ($value->externals->imdb) {
                $show->setImdbId($value->externals->imdb);
            }

            if ($value->genres) {
                $show->setGenres($value->genres);
            }

            $show->setStatus($value->status);

            if ($value->premiered) {
                $show->setPremiered($value->premiered);
            }

            if ($value->ended) {
                $show->setEnded($value->ended);
            }

            $show->setWeight($value->weight);

            if ($value->officialSite) {
                $show->setOfficialSite($value->officialSite);
            }

            $networkName = $value?->network?->name;
            if ($networkName) {
                $show->setNetworkName($networkName);
            }

            $networkCountry = $value?->network?->country?->name;
            if ($networkCountry) {
                $show->setNetworkCountry($networkCountry);
            }

            $webChannelName = $value?->webChannel?->name;
            if ($webChannelName) {
                $show->setWebChannelName($webChannelName);
            }

            $webChannelCountry = $value?->webChannel?->country?->name;
            if ($webChannelCountry) {
                $show->setWebChannelCountry($webChannelCountry);
            }

            $imageMedium = $value?->image?->medium;
            if ($imageMedium) {
                $show->setImageMedium($imageMedium);
            }

            $imageOriginal = $value?->image?->original;
            if ($imageOriginal) {
                $show->setImageOriginal($imageOriginal);
            }

            if ($value->runtime) {
                $show->setRuntime($value->runtime);
            }


            $manager->persist($show);

            if ($count % $batchSize === 0) {
                $manager->flush();
                $manager->clear();
                $count = 1;
            } else {
                $count++;
            }

            $inserted++;
        }

        if ($count > 1) {
            $manager->flush();
            $manager->clear();
        }


        echo "Shows inserted: $inserted" . PHP_EOL;
    }
}
