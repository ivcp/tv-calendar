#!/usr/bin/bash

set -e

cf_ips() {
echo "# https://www.cloudflare.com/ips"

for type in v4 v6; do
echo "# IP$type"
curl -sL "https://www.cloudflare.com/ips-$type/" | sed "s|^|allow |g" | sed "s|\$|;|g"
echo
done

echo "# Generated at $(LC_ALL=C date)"
}

set_real() {
for type in v4 v6; do
echo "# IP$type"
curl -sL "https://www.cloudflare.com/ips-$type/" | sed "s|^|set_real_ip_from |g" | sed "s|\$|;|g"
echo
done

echo "# Generated at $(LC_ALL=C date)"
}

(cf_ips && echo "deny all; # deny all remaining ips") > ./docker/nginx/allow-cloudflare-only.conf
set_real > ./docker/nginx/set-real-ips.conf