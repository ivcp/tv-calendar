<?php

declare(strict_types=1);

namespace App\Services;

use Doctrine\ORM\EntityManager;

class PremieresService
{
    public function __construct(
        private readonly EntityManager $entityManager
    ) {}

    public function run(): void
    {
        $connection = $this->entityManager->getConnection();

        $sql = 'SELECT json_agg(row_to_json(t)) FROM (SELECT e.id, s.name as "showName", e.name as "episodeName",

                s.summary as "showDescription", s.weight, e.season, e.number, e.summary as "episodeSummary", e.airstamp AT TIME ZONE :timeZone AS airtime,

                s.image_original as poster, s.network_name as "networkName", 

                s.web_channel_name as "webChannelName"

                FROM episodes e

                INNER JOIN shows s ON s.id = e.show_id 
                    
                    WHERE e.airstamp >= (date_trunc(:week, CURRENT_TIMESTAMP AT TIME ZONE :timeZone)::date + :start::interval) AT TIME ZONE :timeZone 

                    AND e.airstamp < (date_trunc(:week, CURRENT_TIMESTAMP AT TIME ZONE :timeZone)::date + :end::interval) AT TIME ZONE :timeZone 
                    
                    AND e.number = 1  

                ORDER BY s.weight DESC, e.airstamp ASC) t;';

        $params = [
            'timeZone' => 'America/New_York',
            'week' => 'week',
            'start' => '7 days',
            'end' => '14 days'
        ];

        $result = $connection->fetchOne($sql, $params);

        $data = json_decode($result);

        var_dump(
            $data
        );
    }
}
