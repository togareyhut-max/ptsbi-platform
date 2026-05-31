#!/usr/bin/env bash
# WordPress sandbox belajar — wp-belajar.local (hosts di PC)
# Jalankan di server: bash deploy-belajar-fresh.sh
# Password DB belajar default: PasswordBelajarWordpress
set -euo pipefail

WP_DIR="/home/togaa/wordpress"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OVERRIDE="${BELAJAR_OVERRIDE:-$SCRIPT_DIR/../docker-compose.belajar.yml}"
DEST="$WP_DIR/docker-compose.belajar.yml"
DB_PASS="${BELAJAR_DB_PASS:-PasswordBelajarWordpress}"
DOMAIN="wp-belajar.local"

read_mysql_root() {
  if [[ -n "${MYSQL_ROOT:-}" ]]; then
    echo "$MYSQL_ROOT"
    return
  fi
  if docker ps --format '{{.Names}}' | grep -qx mysql; then
    local p
    p=$(docker exec mysql printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true)
    if [[ -n "$p" ]]; then
      echo "$p"
      return
    fi
  fi
  echo ""
}

mysql_root_exec() {
  local sql=$1
  docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -uroot -e "$sql"
}

MYSQL_ROOT="$(read_mysql_root)"
[[ -n "$MYSQL_ROOT" ]] || {
  echo "ERROR: tidak bisa baca MYSQL_ROOT (container mysql harus jalan)."
  exit 1
}
[[ -f "$WP_DIR/docker-compose.yml" ]] || {
  echo "ERROR: $WP_DIR/docker-compose.yml tidak ada"
  exit 1
}
[[ -f "$OVERRIDE" ]] || {
  echo "ERROR: $OVERRIDE tidak ada. Upload docker-compose.belajar.yml."
  exit 1
}

echo "=============================================="
echo " WP BELAJAR — deploy / sinkron password"
echo " Password belajar: ${DB_PASS}"
echo "=============================================="

if [[ "$(readlink -f "$OVERRIDE")" != "$(readlink -f "$DEST")" ]]; then
  sudo cp -a "$OVERRIDE" "$DEST"
  echo "    Compose: $DEST"
else
  echo "    Compose sudah di $DEST (lewati cp)"
fi

# Traefik server: entrypoints http + https (bukan web/websecure)
if grep -q "entrypoints.http.address" /home/togaa/traefik/docker-compose.yml 2>/dev/null; then
  sudo sed -i 's/entrypoints=web/entrypoints=http/' "$DEST" 2>/dev/null || true
  sudo sed -i 's/entrypoints=websecure/entrypoints=https/' "$DEST" 2>/dev/null || true
  echo "    Entrypoint diset: http (port 80)"
fi

echo "$BELAJAR_DB_PASS=$DB_PASS" | sudo tee "$WP_DIR/.env.belajar" >/dev/null
sudo chmod 600 "$WP_DIR/.env.belajar"

echo "==> [1/4] Database wp_belajar + user belajar..."
mysql_root_exec "
CREATE DATABASE IF NOT EXISTS wp_belajar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'belajar'@'%' IDENTIFIED BY '${DB_PASS}';
ALTER USER 'belajar'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL ON wp_belajar.* TO 'belajar'@'%';
FLUSH PRIVILEGES;
"

echo "==> [2/4] Container wordpress-belajar..."
cd "$WP_DIR"
export BELAJAR_DB_PASS="$DB_PASS"
sudo -E docker compose --env-file .env.belajar \
  -f docker-compose.yml -f docker-compose.belajar.yml \
  up -d --force-recreate wordpress-belajar
docker ps --format '{{.Names}} {{.Status}}' | grep belajar || true

echo "==> [3/4] wp-config.php (jika sudah ada)..."
sleep 6
VOL=$(docker volume inspect wordpress_wordpress_belajar --format '{{.Mountpoint}}' 2>/dev/null || true)
if [[ -n "$VOL" && -f "$VOL/wp-config.php" ]]; then
  sudo sed -i "s/define( *'DB_NAME'.*/define( 'DB_NAME', 'wp_belajar' );/" "$VOL/wp-config.php"
  sudo sed -i "s/define( *'DB_USER'.*/define( 'DB_USER', 'belajar' );/" "$VOL/wp-config.php"
  sudo sed -i "s/define( *'DB_PASSWORD'.*/define( 'DB_PASSWORD', '${DB_PASS}' );/" "$VOL/wp-config.php"
  sudo sed -i "s/define( *'DB_HOST'.*/define( 'DB_HOST', 'mysql' );/" "$VOL/wp-config.php"
  if ! sudo grep -q "WP_HOME" "$VOL/wp-config.php" 2>/dev/null; then
    sudo sed -i "/That's all, stop editing/i define('WP_HOME','http://${DOMAIN}');\ndefine('WP_SITEURL','http://${DOMAIN}');" "$VOL/wp-config.php"
  fi
  echo "    wp-config OK"
else
  echo "    wp-config belum ada — selesaikan wizard WP di browser"
fi

echo "==> [4/4] Tes Traefik..."
curl -sI -H "Host: ${DOMAIN}" http://127.0.0.1:80 | head -1 || true

echo ""
echo "=============================================="
echo " SELESAI"
echo "=============================================="
echo "Hosts (Windows, as Admin):"
echo "  5.175.245.78  wp-belajar.local www.wp-belajar.local"
echo ""
echo "Browser: http://wp-belajar.local"
echo "DB: wp_belajar | user: belajar | pass: ${DB_PASS}"
echo "=============================================="
