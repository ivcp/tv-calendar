
source .env

if [ -z "$ORACLE_PAR" ]; then
    echo "ORACLE_PAR is not set"
    exit
fi

/usr/bin/docker exec -t tv-calendar-db bash -c "pg_dump -U ${DB_USER} -h localhost ${DB_NAME} | gzip > backup.sql.gz"
/usr/bin/docker cp tv-calendar-db:backup.sql.gz .
/usr/bin/docker exec -t tv-calendar-db bash -c "rm backup.sql.gz"

/usr/bin/curl -T backup.sql.gz "${ORACLE_PAR}backup.sql.gz"

export PATH="/usr/bin:$PATH"
/usr/bin/mega-put backup.sql.gz /Backups/TvShowCalendar

/usr/bin/rm backup.sql.gz

