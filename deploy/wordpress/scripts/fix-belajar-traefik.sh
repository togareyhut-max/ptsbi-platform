#!/usr/bin/env bash
# Perbaiki Traefik 404 untuk wp-belajar.local
# Jalankan di server: bash /home/togaa/wordpress/scripts/fix-belajar-traefik.sh
set -euo pipefail

WP_DIR="/home/togaa/wordpress"
MAIN="$WP_DIR/docker-compose.yml"
BELAJAR="$WP_DIR/docker-compose.belajar.yml"

[[ -f "$MAIN" ]] || { echo "ERROR: $MAIN tidak ada"; exit 1; }

# Traefik vm21197: entrypoints = http (:80) dan https (:443), BUKAN web/websecure
EP_HTTP="http"
if grep -q "entrypoints.http.address" /home/togaa/traefik/docker-compose.yml 2>/dev/null; then
  EP_HTTP="http"
elif grep -q "traefik.http.routers.ptsbi-http.entrypoints=" "$MAIN" 2>/dev/null; then
  EP_HTTP=$(grep -m1 "traefik.http.routers.ptsbi-http.entrypoints=" "$MAIN" | sed 's/.*entrypoints=//')
fi

echo "==> Entrypoint HTTP dipakai: ${EP_HTTP}"

sudo tee "$BELAJAR" > /dev/null <<YAML
# Sandbox belajar — HTTP only (wp-belajar.local via hosts)
services:
  wordpress-belajar:
    image: wordpress:6.9.1-php8.2-apache
    container_name: wordpress-belajar
    restart: unless-stopped
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_NAME: wp_belajar
      WORDPRESS_DB_USER: belajar
      WORDPRESS_DB_PASSWORD: \${BELAJAR_DB_PASS:-PasswordBelajarWordpress}
    volumes:
      - wordpress_belajar:/var/www/html
    networks:
      - traefik
    labels:
      - traefik.enable=true
      - traefik.docker.network=traefik
      - traefik.http.routers.belajar.rule=Host(\`wp-belajar.local\`) || Host(\`www.wp-belajar.local\`)
      - traefik.http.routers.belajar.entrypoints=${EP_HTTP}
      - traefik.http.routers.belajar.service=belajar-svc
      - traefik.http.services.belajar-svc.loadbalancer.server.port=80

volumes:
  wordpress_belajar:

networks:
  traefik:
    external: true
YAML

echo "==> Recreate wordpress-belajar..."
cd "$WP_DIR"
export BELAJAR_DB_PASS="${BELAJAR_DB_PASS:-PasswordBelajarWordpress}"
[[ -f .env.belajar ]] && export $(grep -v '^#' .env.belajar | xargs) 2>/dev/null || true

sudo -E docker compose --env-file .env.belajar 2>/dev/null \
  -f docker-compose.yml -f docker-compose.belajar.yml \
  up -d --force-recreate wordpress-belajar 2>/dev/null || \
sudo -E docker compose -f docker-compose.yml -f docker-compose.belajar.yml \
  up -d --force-recreate wordpress-belajar

sleep 4

echo "==> Tes container (harus 200/302):"
docker exec wordpress-belajar curl -sI http://127.0.0.1/ 2>/dev/null | head -1 || echo "    container curl gagal"

echo "==> Tes Traefik port 80:"
curl -sI -H "Host: wp-belajar.local" "http://127.0.0.1:80/" | head -3

echo "==> Label terpasang:"
docker inspect wordpress-belajar --format '{{range \$k,\$v := .Config.Labels}}{{println \$k \$v}}{{end}}' | grep traefik || true

echo ""
echo "PC: hosts -> 5.175.245.78 wp-belajar.local"
echo "Browser: http://wp-belajar.local (bukan https)"
