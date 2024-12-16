<?php

declare(strict_types=1);

namespace App\DataFixtures\Seeds;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class EpisodeSeedDataLoader implements FixtureInterface
{

    public function load(ObjectManager $manager): void
    {


        try {
            /** @disregard P1009 Undefined type */
            $conn = $manager->getConnection();
            $stmt = $conn->prepare('CREATE TABLE temp (data jsonb);');
            $stmt->executeStatement($stmt);
            $stmt = $conn->prepare("COPY temp (data) FROM '/storage/nds_episodes.json';");
            $stmt->executeStatement($stmt);
            $stmt = $conn->prepare("INSERT INTO episodes
            (tv_maze_episode_id, season, number, airstamp, type, summary, name, runtime, image_medium, 
            image_original, created_at, updated_at, tv_maze_show_id)
            SELECT 
            (data->'id')::integer, (data->>'season')::smallint, (data->>'number')::integer, timestamptz (data->>'airstamp'),
            data->>'type', data->>'summary', data->>'name', (data->>'runtime')::smallint, data->'image'->>'medium', 
            data->'image'->>'original', current_timestamp, current_timestamp, (data->'tv_maze_show_id')::integer
            FROM temp;");
            $rows =  $stmt->executeStatement($stmt);
            $stmt = $conn->prepare("UPDATE episodes 
            SET show_id = shows.id
            FROM shows
            WHERE episodes.tv_maze_show_id = shows.tv_maze_id;");
            $linked =  $stmt->executeStatement($stmt);
        } catch (Exception $e) {
            echo "Erorr: " . $e->getMessage() . PHP_EOL;
            exit;
        }


        try {
            $sql = 'DROP TABLE temp;';
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement($stmt);
        } catch (Exception $e) {
            echo "Erorr: " . $e->getMessage() . PHP_EOL;
            exit;
        }



        echo "Episodes inserted: $rows." . PHP_EOL;
        echo "Linked $linked episodes to shows." . PHP_EOL;
    }

    public function getDependencies(): array
    {
        return [ShowSeedDataLoader::class];
    }
}
