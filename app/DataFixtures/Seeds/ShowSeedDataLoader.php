<?php

declare(strict_types=1);

namespace App\DataFixtures\Seeds;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class ShowSeedDataLoader implements FixtureInterface
{
    public function load(ObjectManager $manager): void
    {

        try {
            /** @disregard P1013 Undefined method */
            $conn = $manager->getConnection();
            $stmt = $conn->prepare('CREATE TABLE temp (data jsonb);');
            $stmt->executeStatement($stmt);
            $stmt = $conn->prepare("COPY temp (data) FROM '/storage/nd_shows.json';");
            $stmt->executeStatement($stmt);
            $stmt = $conn->prepare("create function json_array_to_txt(p_input jsonb)
            returns text
            as
            $$
            select string_agg(x.item, ',' order by idx)
            from jsonb_array_elements_text(p_input) with ordinality as x(item, idx);
            $$
            language sql
            immutable;");
            $stmt->executeStatement($stmt);
            $stmt = $conn->prepare("INSERT INTO shows 
          (tv_maze_id, imdb_id, genres, status, premiered, ended, official_site, 
          weight, network_name, network_country, web_channel_name, web_channel_country,
          summary, name, runtime, image_medium, image_original, created_at, updated_at)
          SELECT 
           (data->'id')::integer, data->'externals'->>'imdb', json_array_to_txt(data->'genres'), 
           data->>'status', data->>'premiered', data->>'ended', data->>'officialSite', 
           (data->>'weight')::integer, data->'network'->>'name', data->'network'->'country'->>'name', 
           data->'webChannel'->>'name', data->'webChannel'->'country'->>'name', data->>'summary', data->>'name', 
           (data->>'runtime')::integer, data->'image'->>'medium', data->'image'->>'original',
           current_timestamp, current_timestamp
          FROM temp;");
            $rows =  $stmt->executeStatement($stmt);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . PHP_EOL;
            exit;
        }


        try {
            $sql = 'DROP TABLE temp;';
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement($stmt);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . PHP_EOL;
            exit;
        }


        echo "Shows inserted: $rows." . PHP_EOL;
    }
}
