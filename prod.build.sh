#!/usr/bin/

SECONDS=0

msg () {
    echo -e "\n******* $1 *******\n"
}


source .env

COMPOSE="docker compose -f prod.compose.yml"

#msg "Update Cloudflare IPs"

#source get_cloudflare_ips.sh

msg "Stopping containers"

docker stop tv-calendar-cron 2>/dev/null || true
$COMPOSE down --remove-orphans


msg "Building containers"

$COMPOSE up -d  tv_app tv_nginx tv_db tv_cron tv_adminer --build
docker cp tv-calendar-app:var/www/vendor .
docker cp tv-calendar-app:var/www/public/build ./public/
docker exec tv-calendar-app bash -c './bin/doctrine orm:generate-proxies'
docker exec tv-calendar-app bash -c './bin/doctrine migrations:migrate --no-interaction'

msg "Removing stale images"

docker image prune -f

msg "Finished in $SECONDS seconds"