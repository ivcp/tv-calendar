
source .env

if [ -z "$ORACLE_PAR" ]; then
    echo "ORACLE_PAR is not set"
    exit
fi

docker exec -it tv-calendar-db bash -c "pg_dump -U user -h localhost tv-calendar | gzip > backup.sql.gz"
docker cp tv-calendar-db:backup.sql.gz .
docker exec -it tv-calendar-db bash -c "rm backup.sql.gz"

curl -T backup.sql.gz "${ORACLE_PAR}backup.sql.gz"

rm backup.sql.gz


