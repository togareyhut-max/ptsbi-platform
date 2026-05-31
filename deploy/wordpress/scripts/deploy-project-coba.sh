#!/usr/bin/env bash
# Site baru: project-coba (WordPress fresh)
# TIDAK menyentuh: ptsbi, lpmpjk, kotakita, belajar, tarombo, traefik, mysql
#
#   bash /home/togaa/deploy-project-coba.sh
#   RESET=1 bash /home/togaa/deploy-project-coba.sh   # hapus TOTAL (volume + DB + file WP)
#   export PROJECT_COBA_DB_PASS='PasswordProjectCobaWordpress'
set -euo pipefail

WP_DIR="/home/togaa/wordpress"
TRAEFIK_DIR="/home/togaa/traefik"
MAIN="$WP_DIR/docker-compose.yml"
OVERRIDE="$WP_DIR/docker-compose.project-coba.yml"
ENV_FILE="$WP_DIR/.env.project-coba"

SLUG="project-coba"
SERVICE="wordpress-project-coba"
CONTAINER="wordpress-project-coba"
DB_NAME="wp_project_coba"
DB_USER="project_coba"
DOMAIN="project-coba.local"
ROUTER="projectcoba"
DB_PASS="${PROJECT_COBA_DB_PASS:-PasswordProjectCobaWordpress}"
RESET="${RESET:-0}"

KEEP='traefik|mysql|wordpress-ptsbi|wordpress-lpmpjk|wordpress-kotakita|wordpress-belajar|tarombo-web'

read_mysql_root() {
  if [[ -n "${MYSQL_ROOT:-}" ]]; then echo "$MYSQL_ROOT"; return; fi
  docker exec mysql printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true
}

fail() { echo "ERROR: $*" >&2; exit 1; }

compose_files() {
  local cf="-f docker-compose.yml"
  local extra
  for extra in docker-compose.kotakita.yml docker-compose.belajar.yml docker-compose.project-coba.yml; do
    [[ -f "$WP_DIR/$extra" ]] && cf="$cf -f $extra"
  done
  echo "$cf"
}

# Kumpulkan nama volume yang dipakai site ini (dari container + pola umum compose)
collect_volumes() {
  local -a vols=()
  local v
  vols+=( wordpress_wordpress_project_coba wordpress_project_coba )
  if docker inspect "$CONTAINER" &>/dev/null; then
    while IFS= read -r v; do
      [[ -n "$v" ]] && vols+=( "$v" )
    done < <(docker inspect "$CONTAINER" --format '{{range .Mounts}}{{if .Name}}{{.Name}}{{"\n"}}{{end}}{{end}}' 2>/dev/null || true)
  fi
  while IFS= read -r v; do
    echo "$v" | grep -qiE 'project[._-]?coba|project_coba' || continue
    vols+=( "$v" )
  done < <(docker volume ls -q 2>/dev/null || true)
  # uniq
  printf '%s\n' "${vols[@]}" | awk '!seen[$0]++'
}

wipe_volume_contents() {
  local vol="$1"
  [[ -z "$vol" ]] && return 0
  if ! docker volume inspect "$vol" &>/dev/null; then
    return 0
  fi
  echo "    Kosongkan isi volume: $vol"
  docker run --rm -v "${vol}:/var/www/html" alpine:3.20 sh -c '
    set -e
    cd /var/www/html
    rm -rf ./* ./.[!.]* ./..?* 2>/dev/null || true
    ls -la
  ' || fail "Gagal wipe volume $vol"
}

remove_volumes_for_site() {
  local vol
  while IFS= read -r vol; do
    [[ -z "$vol" ]] && continue
    if docker volume rm -f "$vol" 2>/dev/null; then
      echo "    Volume dihapus: $vol"
      continue
    fi
    wipe_volume_contents "$vol"
    if docker volume rm -f "$vol" 2>/dev/null; then
      echo "    Volume dihapus setelah wipe: $vol"
    else
      echo "    WARN: volume $vol masih terpasang — isi sudah dikosongkan"
    fi
  done < <(collect_volumes)
}

reset_site_fully() {
  local cf
  cf="$(compose_files)"
  echo ""
  echo "==> Reset TOTAL ${SLUG} (container + volume + wp-content + DB)..."

  cd "$WP_DIR"
  if [[ -f "$ENV_FILE" ]]; then
    # Hapus container + named volume service (compose v2)
    docker compose --env-file .env.project-coba $cf stop "$SERVICE" 2>/dev/null || true
    docker compose --env-file .env.project-coba $cf rm -f -s -v "$SERVICE" 2>/dev/null || true
  fi
  docker stop "$CONTAINER" 2>/dev/null || true
  docker rm -f "$CONTAINER" 2>/dev/null || true

  remove_volumes_for_site

  docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -uroot -e "
DROP DATABASE IF EXISTS ${DB_NAME};
DROP USER IF EXISTS '${DB_USER}'@'%';
FLUSH PRIVILEGES;
"
  echo "    Database ${DB_NAME} di-drop."
}

[[ -f "$MAIN" ]] || fail "$MAIN tidak ada"
MYSQL_ROOT="$(read_mysql_root)"
[[ -n "$MYSQL_ROOT" ]] || fail "MYSQL_ROOT tidak terbaca"

echo "=============================================="
echo " DEPLOY ${SLUG} — WordPress fresh"
echo " RESET=${RESET} (1 = hapus TOTAL site ini dulu)"
echo "=============================================="

EP_HTTP="http"
if [[ -f "$TRAEFIK_DIR/docker-compose.yml" ]] && grep -q 'entrypoints.http.address' "$TRAEFIK_DIR/docker-compose.yml"; then
  EP_HTTP="http"
fi
if grep -q 'traefik.http.routers.ptsbi-http.entrypoints=' "$MAIN" 2>/dev/null; then
  EP_HTTP=$(grep -m1 'traefik.http.routers.ptsbi-http.entrypoints=' "$MAIN" | sed 's/.*entrypoints=//')
fi
echo "    Traefik HTTP entrypoint: ${EP_HTTP}"

if [[ "$RESET" == "1" ]]; then
  reset_site_fully
fi

echo ""
echo "==> Site lain (harus Up):"
docker ps --format '  {{.Names}}  {{.Status}}' | grep -E "$KEEP" || fail "Container produksi tidak lengkap!"

echo ""
echo "==> Tulis ${OVERRIDE}..."
tee "$OVERRIDE" > /dev/null <<YAML
# ${DOMAIN} — project coba (HTTP via hosts)
services:
  ${SERVICE}:
    image: wordpress:6.9.1-php8.2-apache
    container_name: ${CONTAINER}
    restart: unless-stopped
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_NAME: ${DB_NAME}
      WORDPRESS_DB_USER: ${DB_USER}
      WORDPRESS_DB_PASSWORD: \${PROJECT_COBA_DB_PASS:-${DB_PASS}}
    volumes:
      - wordpress_project_coba:/var/www/html
    networks:
      - traefik
    labels:
      - traefik.enable=true
      - traefik.docker.network=traefik
      - traefik.http.routers.${ROUTER}.rule=Host(\`${DOMAIN}\`) || Host(\`www.${DOMAIN}\`)
      - traefik.http.routers.${ROUTER}.entrypoints=${EP_HTTP}
      - traefik.http.routers.${ROUTER}.priority=200
      - traefik.http.routers.${ROUTER}.service=${ROUTER}
      - traefik.http.services.${ROUTER}.loadbalancer.server.port=80

volumes:
  wordpress_project_coba:

networks:
  traefik:
    external: true
YAML

echo "PROJECT_COBA_DB_PASS=${DB_PASS}" > "$ENV_FILE"
chmod 600 "$ENV_FILE"

echo "==> Database..."
docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -uroot -e "
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
"

echo "==> Container..."
cd "$WP_DIR"
export PROJECT_COBA_DB_PASS="$DB_PASS"
COMPOSE_FILES="$(compose_files)"

docker compose --env-file .env.project-coba $COMPOSE_FILES up -d "$SERVICE"
sleep 10

# Volume kadang tidak ter-unmount — paksa hapus tema/plugin/upload + wp-config (install ulang)
if [[ "$RESET" == "1" ]]; then
  echo "==> Pastikan wp-content bersih (RESET)..."
  docker exec "$CONTAINER" bash -c '
    set -e
    rm -f /var/www/html/wp-config.php
    # Hapus tema/plugin custom (biarkan twenty* bawaan agar WP tidak mati total)
    find /var/www/html/wp-content/themes -mindepth 1 -maxdepth 1 ! -name "twenty*" -exec rm -rf {} + 2>/dev/null || true
    rm -rf /var/www/html/wp-content/plugins/* /var/www/html/wp-content/uploads/* 2>/dev/null || true
    mkdir -p /var/www/html/wp-content/themes /var/www/html/wp-content/plugins /var/www/html/wp-content/uploads
    chown -R www-data:www-data /var/www/html/wp-content
  ' || fail "Gagal bersihkan wp-content di container"
fi

echo "==> Verifikasi..."
INNER=$(docker exec "$CONTAINER" curl -sI http://127.0.0.1/ 2>/dev/null | head -1 || echo "FAIL")
echo "    Apache: $INNER"
echo "$INNER" | grep -qE '200|302' || fail "Container tidak OK"

TRAEFIK_LINE=$(curl -sI -H "Host: ${DOMAIN}" "http://127.0.0.1:80/" | head -1 || echo "FAIL")
echo "    Traefik: $TRAEFIK_LINE"
echo "$TRAEFIK_LINE" | grep -qE '200|302' || fail "Traefik 404 — cek entrypoints=http di label"

if [[ "$RESET" == "1" ]]; then
  echo "    Tema terpasang:"
  docker exec "$CONTAINER" ls -1 /var/www/html/wp-content/themes 2>/dev/null | sed 's/^/      /' || true
fi

echo ""
echo "=============================================="
echo " BERHASIL — ${SLUG}"
echo "=============================================="
docker ps --format 'table {{.Names}}\t{{.Status}}' | grep -E 'project-coba|ptsbi|lpmpjk|kotakita|belajar' || true
echo ""
echo "Hosts PC:"
echo "  5.175.245.78  ${DOMAIN} www.${DOMAIN}"
echo ""
echo "Browser: http://${DOMAIN}"
echo "DB: ${DB_NAME} | user: ${DB_USER} | pass: ${DB_PASS}"
echo ""
echo "Reset TOTAL (hapus tema/plugin/upload + DB): RESET=1 bash $0"
echo "=============================================="
