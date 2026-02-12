#!/usr/bin/bash

source .env

if [ -z "$ORACLE_PAR" ]; then
    echo "ORACLE_PAR is not set"
    exit
fi

docker exec -t tv-calendar-db bash -c "pg_dump -U ${DB_USER} -h localhost ${DB_NAME} | gzip > backup.sql.gz"
docker cp tv-calendar-db:backup.sql.gz .
docker exec -t tv-calendar-db bash -c "rm backup.sql.gz"

curl -T backup.sql.gz "${ORACLE_PAR}backup.sql.gz"

mega-put backup.sql.gz /Backups/TvShowCalendar
mega-quit

rm backup.sql.gz

