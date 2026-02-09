#!/usr/bin/

SECONDS=0

msg () {
    echo -e "\n******* $1 *******\n"
}


source .env

#msg "Update Cloudflare IPs"

#source get_cloudflare_ips.sh

msg "Stopping containers"

docker compose down --remove-orphans


msg "Building containers"

docker compose -f prod.compose.yml up -d  tv_app tv_nginx tv_db tv_cron --build
docker cp tv-calendar-app:var/www/vendor .
docker cp tv-calendar-app:var/www/public/build ./public/
docker exec tv-calendar-app bash -c './bin/doctrine orm:generate-proxies'
docker exec tv-calendar-app bash -c './bin/doctrine migrations:migrate --no-interaction'
chmod 1777 /tmp
docker cp /etc/ssl/tvshowcalendar.pem tv-calendar-nginx:etc/ssl
docker cp /etc/ssl/tvshowcalendar.key tv-calendar-nginx:etc/ssl

msg "Removing stale images"

docker image prune -f

msg "Finished in $SECONDS seconds"