#!/usr/bin/env bash
# restore-kotakita-files.sh — copy backup + set wp-config DB
set -euo pipefail

RESTORE="${RESTORE_DIR:-$HOME/kotakita-restore}"
DB_PASS="${KOTAKITA_DB_PASS:-PasswordKotakitaKuat}"

if [[ ! -d "$RESTORE/wp-content" ]]; then
  echo "ERROR: $RESTORE/wp-content tidak ada. Extract kotakita.tar.gz dulu."
  exit 1
fi

VOL=$(docker volume inspect wordpress_wordpress_kotakita --format '{{.Mountpoint}}')
echo "Volume: $VOL"

echo "==> Copy file WordPress..."
sudo rsync -a "$RESTORE/" "$VOL/" \
  --exclude kotakita.tar.gz \
  --exclude error_log

echo "==> Update wp-config.php (DB + URL)..."
sudo sed -i "s/define( *'DB_NAME'.*/define( 'DB_NAME', 'wp_kotakita' );/" "$VOL/wp-config.php"
sudo sed -i "s/define( *'DB_USER'.*/define( 'DB_USER', 'kotakita' );/" "$VOL/wp-config.php"
sudo sed -i "s/define( *'DB_PASSWORD'.*/define( 'DB_PASSWORD', '${DB_PASS}' );/" "$VOL/wp-config.php"
sudo sed -i "s/define( *'DB_HOST'.*/define( 'DB_HOST', 'mysql' );/" "$VOL/wp-config.php"

if ! grep -q "WP_HOME" "$VOL/wp-config.php"; then
  sudo sed -i "/That's all, stop editing/i\\
define( 'WP_HOME', 'https://kotakita.net' );\\
define( 'WP_SITEURL', 'https://kotakita.net' );\\
" "$VOL/wp-config.php"
else
  sudo sed -i "s|define( *'WP_HOME'.*|define( 'WP_HOME', 'https://kotakita.net' );|" "$VOL/wp-config.php"
  sudo sed -i "s|define( *'WP_SITEURL'.*|define( 'WP_SITEURL', 'https://kotakita.net' );|" "$VOL/wp-config.php"
fi

SQL=$(find "$RESTORE" -maxdepth 3 -name "*.sql" -type f | head -1)
if [[ -n "$SQL" ]]; then
  echo "==> Import database: $SQL"
  docker exec -i mysql mysql -ukotakita -p"${DB_PASS}" wp_kotakita < "$SQL"
else
  echo "PERINGATAN: Tidak ada file .sql — minta dump database ke teman."
fi

docker restart wordpress-kotakita
echo "Selesai. Tes: https://kotakita.net (hosts file -> 5.175.245.78)"
