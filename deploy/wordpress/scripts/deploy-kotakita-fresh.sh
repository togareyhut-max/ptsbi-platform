#!/usr/bin/env bash
# WordPress KOSONG kotakita.net — sama struktur ptsbi/lpmpjk.
# Jalankan SETELAH cleanup:  bash deploy-kotakita-fresh.sh
# Password kotakita BARU (bukan dari backup): default PasswordKotakitaKuat — ganti dengan:
#   export KOTAKITA_DB_PASS='password-baru-anda'
set -euo pipefail

WP_DIR="/home/togaa/wordpress"
MYSQL_COMPOSE="/home/togaa/mysql/docker-compose.yml"
DB_PASS="${KOTAKITA_DB_PASS:-PasswordKotakitaKuat}"
DOMAIN="kotakita.net"

read_mysql_root() {
  if [[ -n "${MYSQL_ROOT:-}" ]]; then echo "$MYSQL_ROOT"; return; fi
  if [[ -f "$MYSQL_COMPOSE" ]]; then
    local p
    p=$(grep -m1 'MYSQL_ROOT_PASSWORD:' "$MYSQL_COMPOSE" | sed -E "s/.*MYSQL_ROOT_PASSWORD:[[:space:]]*['\"]?([^'\"]+)['\"]?.*/\1/" | sed 's/\$\$/\$/g')
    [[ -n "$p" ]] && { echo "$p"; return; }
  fi
  echo ""
}

MYSQL_ROOT="$(read_mysql_root)"
[[ -n "$MYSQL_ROOT" ]] || { echo "ERROR: tidak bisa baca MYSQL_ROOT dari $MYSQL_COMPOSE"; exit 1; }

[[ -f "$WP_DIR/docker-compose.yml" ]] || { echo "ERROR: $WP_DIR/docker-compose.yml tidak ada"; exit 1; }

echo "=============================================="
echo " KOTAKITA.NET — WordPress fresh (UpdraftPlus)"
echo "=============================================="

# Samakan Traefik entrypoints dengan ptsbi
EP="websecure"
CR="letsencrypt"
if grep -q "traefik.http.routers.ptsbi.entrypoints=" "$WP_DIR/docker-compose.yml" 2>/dev/null; then
  EP=$(grep -m1 "traefik.http.routers.ptsbi.entrypoints=" "$WP_DIR/docker-compose.yml" | sed 's/.*entrypoints=//')
  CR=$(grep -m1 "traefik.http.routers.ptsbi.tls.certresolver=" "$WP_DIR/docker-compose.yml" | sed 's/.*certresolver=//')
fi

# Tulis override compose (tidak edit compose utama)
sudo tee "$WP_DIR/docker-compose.kotakita.yml" > /dev/null <<YAML
services:
  wordpress-kotakita:
    image: wordpress:6.9.1-php8.2-apache
    container_name: wordpress-kotakita
    restart: unless-stopped
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_NAME: wp_kotakita
      WORDPRESS_DB_USER: kotakita
      WORDPRESS_DB_PASSWORD: \${KOTAKITA_DB_PASS:-${DB_PASS}}
    volumes:
      - wordpress_kotakita:/var/www/html
    networks:
      - traefik
    labels:
      - traefik.enable=true
      - traefik.http.routers.kotakita.rule=Host(\`${DOMAIN}\`) || Host(\`www.${DOMAIN}\`)
      - traefik.http.routers.kotakita.entrypoints=${EP}
      - traefik.http.routers.kotakita.tls=true
      - traefik.http.routers.kotakita.tls.certresolver=${CR}
      - traefik.http.services.kotakita.loadbalancer.server.port=80
      - traefik.http.routers.kotakita-http.rule=Host(\`${DOMAIN}\`) || Host(\`www.${DOMAIN}\`)
      - traefik.http.routers.kotakita-http.entrypoints=web
      - traefik.http.routers.kotakita-http.middlewares=kotakita-https
      - traefik.http.middlewares.kotakita-https.redirectscheme.scheme=https
      - traefik.http.middlewares.kotakita-https.redirectscheme.permanent=true

volumes:
  wordpress_kotakita:
YAML

echo "==> [1/4] Database..."
docker exec mysql mysql -uroot -p"$MYSQL_ROOT" -e "
CREATE DATABASE IF NOT EXISTS wp_kotakita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'kotakita'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL ON wp_kotakita.* TO 'kotakita'@'%';
FLUSH PRIVILEGES;
"

echo "==> [2/4] Container WordPress kosong..."
cd "$WP_DIR"
export KOTAKITA_DB_PASS="$DB_PASS"
sudo -E docker compose -f docker-compose.yml -f docker-compose.kotakita.yml up -d wordpress-kotakita
docker ps --format '{{.Names}} {{.Status}}' | grep kotakita

echo "==> [3/4] Tunggu WordPress siap..."
sleep 8
VOL=$(docker volume inspect wordpress_wordpress_kotakita --format '{{.Mountpoint}}')

# wp-config: URL production (UpdraftPlus butuh ini)
if [[ -f "$VOL/wp-config.php" ]]; then
  sudo sed -i "s/define( *'DB_NAME'.*/define( 'DB_NAME', 'wp_kotakita' );/" "$VOL/wp-config.php"
  sudo sed -i "s/define( *'DB_USER'.*/define( 'DB_USER', 'kotakita' );/" "$VOL/wp-config.php"
  sudo sed -i "s/define( *'DB_PASSWORD'.*/define( 'DB_PASSWORD', '${DB_PASS}' );/" "$VOL/wp-config.php"
  sudo sed -i "s/define( *'DB_HOST'.*/define( 'DB_HOST', 'mysql' );/" "$VOL/wp-config.php"
  if ! sudo grep -q "WP_HOME" "$VOL/wp-config.php" 2>/dev/null; then
    sudo sed -i "/That's all, stop editing/i define('WP_HOME','https://${DOMAIN}');\ndefine('WP_SITEURL','https://${DOMAIN}');" "$VOL/wp-config.php"
  fi
fi

echo "==> [4/4] Tes Traefik..."
curl -sI -H "Host: ${DOMAIN}" http://127.0.0.1:80 | head -1 || true

echo ""
echo "=============================================="
echo " SELESAI — WordPress fresh siap UpdraftPlus"
echo "=============================================="
echo ""
echo "LANGKAH ANDA:"
echo ""
echo "1. Laptop — file hosts (tes sebelum DNS):"
echo "   5.175.245.78  kotakita.net www.kotakita.net"
echo ""
echo "2. Browser: https://kotakita.net"
echo "   Selesaikan instalasi WP (bahasa, admin, password baru)"
echo ""
echo "3. Plugin → Add New → install UpdraftPlus"
echo ""
echo "4. UpdraftPlus → Restore → upload backup dari situs lama"
echo "   (database + plugins + themes + uploads)"
echo ""
echo "5. Setelah restore OK → minta teman DNS:"
echo "   A @ dan www → 5.175.245.78"
echo ""
echo "6. Hapus baris hosts di laptop"
echo ""
echo "ptsbi.org & lpmpjk.org TIDAK disentuh."
echo "=============================================="
