#!/usr/bin/env bash
# setup-kotakita.sh — tambah wordpress-kotakita ke compose (pola ptsbi) + up container
# Jalankan di server: bash ~/setup-kotakita.sh
set -euo pipefail

COMPOSE="/home/togaa/wordpress/docker-compose.yml"
DB_PASS="${KOTAKITA_DB_PASS:-PasswordKotakitaKuat}"
# Password root MySQL TIDAK di-hardcode. Wajib disuplai lewat env saat menjalankan:
#   MYSQL_ROOT='passwordRootAnda' bash ~/setup-kotakita.sh
# Atau biarkan kosong jika container mysql sudah mengekspos MYSQL_ROOT_PASSWORD.
MYSQL_ROOT="${MYSQL_ROOT:-}"

if [[ ! -f "$COMPOSE" ]]; then
  echo "ERROR: Tidak ada $COMPOSE"
  exit 1
fi

if [[ -z "$MYSQL_ROOT" ]]; then
  # Coba ambil dari container mysql yang berjalan, kalau tidak ada minta lewat env.
  MYSQL_ROOT="$(docker exec mysql printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true)"
  if [[ -z "$MYSQL_ROOT" ]]; then
    echo "ERROR: MYSQL_ROOT tidak diset. Jalankan ulang dengan:"
    echo "  MYSQL_ROOT='passwordRootAnda' bash $0"
    exit 1
  fi
fi

if ! grep -q 'wordpress-ptsbi:' "$COMPOSE"; then
  echo "ERROR: wordpress-ptsbi tidak ditemukan — salin manual dari lpmpjk."
  exit 1
fi

echo "==> Backup compose..."
cp -a "$COMPOSE" "${COMPOSE}.bak.$(date +%Y%m%d-%H%M%S)"

echo "==> Tambah service wordpress-kotakita (jika belum ada)..."
export COMPOSE DB_PASS
python3 << 'PY'
import os, re, sys

path = "/home/togaa/wordpress/docker-compose.yml"
db_pass = os.environ.get("DB_PASS", "PasswordKotakitaKuat")
text = open(path, encoding="utf-8").read()

if "wordpress-kotakita:" in text:
    print("   Sudah ada wordpress-kotakita — lewati insert.")
    sys.exit(0)

# Ambil entrypoints + certresolver dari ptsbi (samakan Traefik)
m_ep = re.search(
    r"traefik\.http\.routers\.ptsbi\.entrypoints=(\S+)",
    text,
)
m_cr = re.search(
    r"traefik\.http\.routers\.ptsbi\.tls\.certresolver=(\S+)",
    text,
)
entrypoint = m_ep.group(1) if m_ep else "websecure"
certresolver = m_cr.group(1) if m_cr else "letsencrypt"

# HTTP redirect middleware (opsional di ptsbi)
has_http = "traefik.http.routers.ptsbi-http" in text

service = f"""  wordpress-kotakita:
    image: wordpress:6.9.1-php8.2-apache
    container_name: wordpress-kotakita
    restart: unless-stopped
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_NAME: wp_kotakita
      WORDPRESS_DB_USER: kotakita
      WORDPRESS_DB_PASSWORD: {db_pass}
    volumes:
      - wordpress_kotakita:/var/www/html
    networks:
      - traefik
    labels:
      - traefik.enable=true
      - traefik.http.routers.kotakita.rule=Host(`kotakita.net`) || Host(`www.kotakita.net`)
      - traefik.http.routers.kotakita.entrypoints={entrypoint}
      - traefik.http.routers.kotakita.tls=true
      - traefik.http.routers.kotakita.tls.certresolver={certresolver}
      - traefik.http.services.kotakita.loadbalancer.server.port=80
"""

if has_http:
    service += """      - traefik.http.routers.kotakita-http.rule=Host(`kotakita.net`) || Host(`www.kotakita.net`)
      - traefik.http.routers.kotakita-http.entrypoints=web
      - traefik.http.routers.kotakita-http.middlewares=kotakita-redirect
      - traefik.http.middlewares.kotakita-redirect.redirectscheme.scheme=https
      - traefik.http.middlewares.kotakita-redirect.redirectscheme.permanent=true
"""

# Sisipkan sebelum "volumes:" tingkat root
if not re.search(r"^volumes:\s*$", text, re.M):
    print("ERROR: tidak menemukan baris 'volumes:' di compose.")
    sys.exit(1)

text = re.sub(r"^volumes:\s*$", service + "\nvolumes:", text, count=1, flags=re.M)

if "wordpress_kotakita:" not in text:
    text = re.sub(
        r"^(volumes:\s*\n)",
        r"\1  wordpress_kotakita:\n",
        text,
        count=1,
        flags=re.M,
    )

open(path, "w", encoding="utf-8").write(text)
print("   Service + volume ditambahkan.")
PY

echo "==> Buat database wp_kotakita (jika belum)..."
docker exec mysql mysql -uroot -p"$MYSQL_ROOT" -e "
CREATE DATABASE IF NOT EXISTS wp_kotakita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'kotakita'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL ON wp_kotakita.* TO 'kotakita'@'%';
FLUSH PRIVILEGES;
" 2>/dev/null || {
  echo "   Gagal auto DB — buat manual: docker exec -it mysql mysql -uroot -p"
}

echo "==> Start container..."
cd /home/togaa/wordpress
docker compose up -d wordpress-kotakita
docker ps --format 'table {{.Names}}\t{{.Status}}' | grep -E 'kotakita|NAMES'

echo ""
echo "Selesai. Lanjut restore file:"
echo "  VOL=\$(docker volume inspect wordpress_wordpress_kotakita --format '{{.Mountpoint}}')"
echo "  sudo rsync -a ~/kotakita-restore/ \"\$VOL/\" --exclude kotakita.tar.gz --exclude error_log"
echo "  sudo sed -i \"s/define( 'DB_NAME'.*/define( 'DB_NAME', 'wp_kotakita' );/\" \"\$VOL/wp-config.php\""
echo "  # ... (edit DB_USER, DB_PASSWORD, WP_HOME di wp-config)"
echo "  docker restart wordpress-kotakita"
