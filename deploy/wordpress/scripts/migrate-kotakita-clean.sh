#!/usr/bin/env bash
# =============================================================================
# migrate-kotakita-clean.sh — BERSIH + deploy kotakita sekali jalan
#
# Jalankan di server (ssh vm21197):
#   cd ~/kotakita-restore
#   bash migrate-kotakita-clean.sh
#
# Prasyarat:
#   - Backup sudah di ~/kotakita-restore/ (wp-content, wp-config, dll.)
#   - File docker-compose.kotakita.yml di folder yang sama dengan script ini
#   - MYSQL_ROOT password (dari /home/togaa/mysql/docker-compose.yml)
# =============================================================================
set -euo pipefail

# Cari dump WordPress (bukan .sql di dalam plugin seperti LiteSpeed)
find_database_sql() {
  local root="$1"
  local f=""
  for f in \
    "$root/kotakita_news.sql" \
    "$root/kotakita.sql" \
    "$root/database.sql"; do
    [[ -f "$f" ]] && { echo "$f"; return 0; }
  done
  while IFS= read -r f; do
    [[ -f "$f" ]] || continue
    if head -20 "$f" | grep -qiE 'CREATE TABLE|INSERT INTO|wp_posts|wp_options'; then
      echo "$f"
      return 0
    fi
  done < <(find "$root" -maxdepth 2 -name "*.sql" -type f ! -path "*/wp-content/*" 2>/dev/null)
  return 1
}

WP_DIR="/home/togaa/wordpress"
RESTORE="${RESTORE_DIR:-$HOME/kotakita-restore}"
DB_PASS="${KOTAKITA_DB_PASS:-PasswordKotakitaKuat}"
MYSQL_ROOT="${MYSQL_ROOT:-sr?yH8U+Vb\$3<PCG}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OVERRIDE="${KOTAKITA_OVERRIDE:-$SCRIPT_DIR/docker-compose.kotakita.yml}"

echo "=============================================="
echo " KOTAKITA — deploy bersih"
echo "=============================================="

# --- 0. Cek prasyarat ---
[[ -d "$RESTORE/wp-content" ]] || { echo "ERROR: $RESTORE/wp-content tidak ada. Extract kotakita.tar.gz dulu."; exit 1; }
[[ -f "$WP_DIR/docker-compose.yml" ]] || { echo "ERROR: $WP_DIR/docker-compose.yml tidak ada."; exit 1; }
[[ -f "$OVERRIDE" ]] || { echo "ERROR: $OVERRIDE tidak ada. Upload docker-compose.kotakita.yml ke server."; exit 1; }

# Samakan entrypoints Traefik dengan ptsbi (jika beda nama)
if grep -q "traefik.http.routers.ptsbi.entrypoints=" "$WP_DIR/docker-compose.yml" 2>/dev/null; then
  EP=$(grep -m1 "traefik.http.routers.ptsbi.entrypoints=" "$WP_DIR/docker-compose.yml" | sed 's/.*entrypoints=//')
  CR=$(grep -m1 "traefik.http.routers.ptsbi.tls.certresolver=" "$WP_DIR/docker-compose.yml" | sed 's/.*certresolver=//')
  sed -i "s/entrypoints=websecure/entrypoints=${EP}/" "$OVERRIDE"
  sed -i "s/certresolver=letsencrypt/certresolver=${CR}/" "$OVERRIDE"
fi

# --- 1. Bersihkan sisa kotakita yang tambal sulam ---
echo "==> [1/6] Hapus container & volume kotakita lama..."
sudo docker stop wordpress-kotakita 2>/dev/null || true
sudo docker rm wordpress-kotakita 2>/dev/null || true
sudo docker volume rm wordpress_wordpress_kotakita 2>/dev/null || true

# Kembalikan compose utama jika pernah rusak (hapus blok kotakita manual)
if grep -q "wordpress-kotakita:" "$WP_DIR/docker-compose.yml" 2>/dev/null; then
  echo "    Menghapus wordpress-kotakita dari compose utama (pakai file override terpisah)..."
  BACKUP="$WP_DIR/docker-compose.yml.bak-before-kotakita-$(date +%Y%m%d-%H%M%S)"
  sudo cp -a "$WP_DIR/docker-compose.yml" "$BACKUP"
  sudo python3 << 'PY'
import re
path = "/home/togaa/wordpress/docker-compose.yml"
text = open(path, encoding="utf-8").read()
text = re.sub(
    r"\n  wordpress-kotakita:.*?(?=\n  wordpress-|\n\nnetworks:|\nnetworks:)",
    "",
    text,
    count=1,
    flags=re.S,
)
text = re.sub(r"\n  wordpress_kotakita:\n", "\n", text)
open(path, "w", encoding="utf-8").write(text)
print("    Blok kotakita di compose utama dihapus.")
PY
fi

sudo cp -a "$OVERRIDE" "$WP_DIR/docker-compose.kotakita.yml"
echo "    Override: $WP_DIR/docker-compose.kotakita.yml"

# --- 2. Database ---
echo "==> [2/6] Database wp_kotakita..."
docker exec mysql mysql -uroot -p"$MYSQL_ROOT" -e "
DROP DATABASE IF EXISTS wp_kotakita;
CREATE DATABASE wp_kotakita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS 'kotakita'@'%';
CREATE USER 'kotakita'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL ON wp_kotakita.* TO 'kotakita'@'%';
FLUSH PRIVILEGES;
"

# --- 3. Container ---
echo "==> [3/6] Start container..."
cd "$WP_DIR"
export KOTAKITA_DB_PASS="$DB_PASS"
sudo -E docker compose -f docker-compose.yml -f docker-compose.kotakita.yml up -d wordpress-kotakita
docker ps --format '{{.Names}} {{.Status}}' | grep kotakita

# --- 4. Copy file ---
echo "==> [4/6] Restore file WordPress..."
VOL=$(docker volume inspect wordpress_wordpress_kotakita --format '{{.Mountpoint}}')
echo "    Volume: $VOL"
sudo rsync -a "$RESTORE/" "$VOL/" \
  --exclude kotakita.tar.gz \
  --exclude error_log \
  --exclude '*.tar.gz'

# --- 5. wp-config ---
echo "==> [5/6] wp-config.php..."
sudo sed -i "s/define( *'DB_NAME'.*/define( 'DB_NAME', 'wp_kotakita' );/" "$VOL/wp-config.php"
sudo sed -i "s/define( *'DB_USER'.*/define( 'DB_USER', 'kotakita' );/" "$VOL/wp-config.php"
sudo sed -i "s/define( *'DB_PASSWORD'.*/define( 'DB_PASSWORD', '${DB_PASS}' );/" "$VOL/wp-config.php"
sudo sed -i "s/define( *'DB_HOST'.*/define( 'DB_HOST', 'mysql' );/" "$VOL/wp-config.php"
if sudo grep -q "WP_HOME" "$VOL/wp-config.php" 2>/dev/null; then
  sudo sed -i "s|define( *'WP_HOME'.*|define( 'WP_HOME', 'https://kotakita.net' );|" "$VOL/wp-config.php"
  sudo sed -i "s|define( *'WP_SITEURL'.*|define( 'WP_SITEURL', 'https://kotakita.net' );|" "$VOL/wp-config.php"
else
  sudo sed -i "/That's all, stop editing/i define('WP_HOME','https://kotakita.net');\ndefine('WP_SITEURL','https://kotakita.net');" "$VOL/wp-config.php"
fi

# --- 6. Import SQL (dump database saja, bukan file plugin) ---
SQL=""
if SQL=$(find_database_sql "$RESTORE"); then
  echo "==> [6/6] Import database: $SQL"
  if docker exec -i mysql mysql -ukotakita -p"${DB_PASS}" wp_kotakita < "$SQL"; then
    echo "    Import OK"
  else
    echo "    ERROR import — cek file SQL atau minta dump baru ke teman."
  fi
else
  echo "==> [6/6] Tidak ada dump database (.sql) di backup."
  echo "    File di wp-content/plugins/ BUKAN database WordPress."
  echo "    Minta teman export phpMyAdmin -> upload kotakita_news.sql ke ~/kotakita-restore/"
fi

docker restart wordpress-kotakita
sleep 3

# --- Tes ---
echo ""
echo "==> Tes routing..."
curl -sI -H "Host: kotakita.net" http://127.0.0.1:80 | head -1 || true
curl -sI -H "Host: www.kotakita.net" http://127.0.0.1:80 | head -1 || true

echo ""
echo "=============================================="
echo " SELESAI"
echo " Tes laptop: hosts -> 5.175.245.78 kotakita.net"
echo " Lalu buka https://kotakita.net"
echo " DNS teman belum dipindah = publik masih ke hosting lama (normal)"
echo "=============================================="
