#!/usr/bin/

SECONDS=0

msg () {
    echo -e "\n******* $1 *******\n"
}


source .env


msg "Stopping containers"

sudo docker compose down --remove-orphans

msg "Building containers"

sudo docker compose -f prod.compose.yml up -d  app nginx db cron --build
sudo docker cp tv-calendar-app:var/www/vendor .
sudo docker cp tv-calendar-app:var/www/public/build ./public/
sudo docker exec -t tv-calendar-app bash -c './bin/doctrine orm:generate-proxies'
sudo chmod 1777 /tmp
sudo docker cp /etc/ssl/tvshowcalendar.pem tv-calendar-nginx:etc/ssl
sudo docker cp /etc/ssl/tvshowcalendar.key tv-calendar-nginx:etc/ssl

msg "Removing stale images"

sudo docker image prune -f

msg "Finished in $SECONDS seconds"