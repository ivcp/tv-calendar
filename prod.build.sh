#!/usr/bin/

SECONDS=0

msg () {
    echo -e "\n******* $1 *******\n"
}


source .env


msg "Pulling from github" 

git pull

msg "Stopping containers"

dufo docker compose down --remove-orphans

msg "Building containers"

sudo docker compose -f prod.compose.yml up -d  app nginx db cron --build`

msg "Removing stale images"

sudo docker image prune -f

msg "Finished in $SECONDS seconds"