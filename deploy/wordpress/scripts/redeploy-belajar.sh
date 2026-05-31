#!/usr/bin/env bash
# Hapus TOTAL wp-belajar + deploy ulang (pola sama kotakita, entrypoint http/https server).
# TIDAK menyentuh: ptsbi, lpmpjk, kotakita, tarombo, traefik, mysql
#
#   bash /home/togaa/redeploy-belajar.sh
#   export BELAJAR_DB_PASS='password-anda'   # opsional
set -euo pipefail

WP_DIR="/home/togaa/wordpress"
TRAEFIK_DIR="/home/togaa/traefik"
MAIN="$WP_DIR/docker-compose.yml"
BELAJAR="$WP_DIR/docker-compose.belajar.yml"
KOTAKITA="$WP_DIR/docker-compose.kotakita.yml"
DOMAIN="wp-belajar.local"
DB_PASS="${BELAJAR_DB_PASS:-PasswordBelajarWordpress}"

KEEP='traefik|mysql|wordpress-ptsbi|wordpress-lpmpjk|wordpress-kotakita|tarombo-web'

read_mysql_root() {
  if [[ -n "${MYSQL_ROOT:-}" ]]; then echo "$MYSQL_ROOT"; return; fi
  docker exec mysql printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true
}

fail() { echo "ERROR: $*" >&2; exit 1; }

[[ -f "$MAIN" ]] || fail "$MAIN tidak ada"
MYSQL_ROOT="$(read_mysql_root)"
[[ -n "$MYSQL_ROOT" ]] || fail "MYSQL_ROOT tidak terbaca (container mysql?)"

echo "=============================================="
echo " REDEPLOY wp-belajar — bersih + baru"
echo " Site lain TIDAK disentuh"
echo "=============================================="

# --- Entrypoint: baca dari ptsbi (sama seperti deploy kotakita kemarin) ---
EP_HTTP="http"
EP_HTTPS="https"
if [[ -f "$TRAEFIK_DIR/docker-compose.yml" ]] && grep -q 'entrypoints.http.address' "$TRAEFIK_DIR/docker-compose.yml"; then
  EP_HTTP="http"
  EP_HTTPS="https"
fi
if grep -q 'traefik.http.routers.ptsbi-http.entrypoints=' "$MAIN" 2>/dev/null; then
  EP_HTTP=$(grep -m1 'traefik.http.routers.ptsbi-http.entrypoints=' "$MAIN" | sed 's/.*entrypoints=//')
fi
if grep -q 'traefik.http.routers.ptsbi.entrypoints=' "$MAIN" 2>/dev/null; then
  EP_HTTPS=$(grep -m1 'traefik.http.routers.ptsbi.entrypoints=' "$MAIN" | sed 's/.*entrypoints=//')
fi
echo "    Traefik entrypoints: HTTP=${EP_HTTP} HTTPS=${EP_HTTPS}"

# ========== FASE 1: HAPUS BELAJAR ==========
echo ""
echo "==> [1/5] Hapus container & volume belajar..."
docker stop wordpress-belajar 2>/dev/null || true
docker rm -f wordpress-belajar 2>/dev/null || true
docker volume rm wordpress_wordpress_belajar wordpress_belajar 2>/dev/null || true
docker volume ls -q | while read -r v; do
  echo "$v" | grep -qi belajar || continue
  docker volume rm "$v" 2>/dev/null || true
done

rm -f "$BELAJAR" "$WP_DIR/.env.belajar"
find "$WP_DIR" -maxdepth 1 -type f -name '*belajar*' -delete 2>/dev/null || true
rm -rf /home/togaa/belajar-restore /home/togaa/belajar-* 2>/dev/null || true

if grep -q 'wordpress-belajar:' "$MAIN" 2>/dev/null; then
  cp -a "$MAIN" "$MAIN.bak-before-redeploy-$(date +%Y%m%d-%H%M%S)"
  python3 << 'PY'
import re
path = "/home/togaa/wordpress/docker-compose.yml"
text = open(path, encoding="utf-8").read()
text = re.sub(
    r"\n  wordpress-belajar:.*?(?=\n  wordpress-|\n\nnetworks:|\nnetworks:)",
    "",
    text,
    count=1,
    flags=re.S,
)
text = re.sub(r"\n  wordpress_belajar:\n", "\n", text)
open(path, "w", encoding="utf-8").write(text)
PY
  echo "    Blok belajar di compose utama dihapus"
fi

echo "==> [2/5] Hapus database wp_belajar..."
docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -uroot -e "
DROP DATABASE IF EXISTS wp_belajar;
DROP USER IF EXISTS 'belajar'@'%';
FLUSH PRIVILEGES;
"

# Pastikan site produksi masih up
echo ""
echo "==> Site produksi (harus Up):"
docker ps --format '  {{.Names}}  {{.Status}}' | grep -E "$KEEP" || fail "Ada container produksi yang tidak jalan!"

# ========== FASE 2: COMPOSE BELAJAR (HTTP saja, tanpa redirect HTTPS) ==========
echo ""
echo "==> [3/5] Tulis docker-compose.belajar.yml (entrypoints=${EP_HTTP})..."
tee "$BELAJAR" > /dev/null <<YAML
# wp-belajar.local — sandbox belajar (HTTP via hosts)
services:
  wordpress-belajar:
    image: wordpress:6.9.1-php8.2-apache
    container_name: wordpress-belajar
    restart: unless-stopped
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_NAME: wp_belajar
      WORDPRESS_DB_USER: belajar
      WORDPRESS_DB_PASSWORD: \${BELAJAR_DB_PASS:-${DB_PASS}}
    volumes:
      - wordpress_belajar:/var/www/html
    networks:
      - traefik
    labels:
      - traefik.enable=true
      - traefik.docker.network=traefik
      - traefik.http.routers.belajar.rule=Host(\`${DOMAIN}\`) || Host(\`www.${DOMAIN}\`)
      - traefik.http.routers.belajar.entrypoints=${EP_HTTP}
      - traefik.http.routers.belajar.priority=200
      - traefik.http.routers.belajar.service=belajar
      - traefik.http.services.belajar.loadbalancer.server.port=80

volumes:
  wordpress_belajar:

networks:
  traefik:
    external: true
YAML

echo "BELAJAR_DB_PASS=${DB_PASS}" > "$WP_DIR/.env.belajar"
chmod 600 "$WP_DIR/.env.belajar"

echo "==> [4/5] Database + container..."
docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -uroot -e "
CREATE DATABASE wp_belajar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'belajar'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL ON wp_belajar.* TO 'belajar'@'%';
FLUSH PRIVILEGES;
"

cd "$WP_DIR"
export BELAJAR_DB_PASS="$DB_PASS"
COMPOSE_FILES="-f docker-compose.yml"
[[ -f "$KOTAKITA" ]] && COMPOSE_FILES="$COMPOSE_FILES -f docker-compose.kotakita.yml"
COMPOSE_FILES="$COMPOSE_FILES -f docker-compose.belajar.yml"

# -f kotakita.yml supaya kotakita tidak jadi "orphan"
docker compose --env-file .env.belajar $COMPOSE_FILES up -d wordpress-belajar
sleep 8

# ========== FASE 3: VERIFIKASI (gagal = exit 1) ==========
echo ""
echo "==> [5/5] Verifikasi..."

INNER=$(docker exec wordpress-belajar curl -sI http://127.0.0.1/ 2>/dev/null | head -1 || echo "FAIL")
echo "    Container Apache: $INNER"
echo "$INNER" | grep -qE '200|302' || fail "Apache di dalam container tidak merespons"

TRAEFIK_LINE=$(curl -sI -H "Host: ${DOMAIN}" "http://127.0.0.1:80/" | head -1 || echo "FAIL")
echo "    Traefik Host ${DOMAIN}: $TRAEFIK_LINE"
echo "$TRAEFIK_LINE" | grep -qE '200|302' || fail "Traefik masih 404 — deploy gagal. Site lain tidak diubah."

echo ""
echo "=============================================="
echo " BERHASIL"
echo "=============================================="
docker ps --format 'table {{.Names}}\t{{.Status}}' | grep -E 'belajar|ptsbi|lpmpjk|kotakita|traefik|mysql|tarombo' || true
echo ""
echo "PC (Administrator) — C:\\Windows\\System32\\drivers\\etc\\hosts:"
echo "  5.175.245.78  wp-belajar.local www.wp-belajar.local"
echo ""
echo "Browser: http://wp-belajar.local"
echo "DB pass: ${DB_PASS}"
echo ""
echo "ptsbi, lpmpjk, kotakita — tidak disentuh."
echo "=============================================="
