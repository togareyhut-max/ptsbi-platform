#!/usr/bin/env bash
# Pasang / update plugin ptsbi-premium ke container WordPress
# Sumber di host: /home/togaa/ptsbi-premium (folder) ATAU unzip dari ptsbi-premium.zip
#
#   bash install-ptsbi-premium.sh
#   CONTAINER=wordpress-ptsbi bash install-ptsbi-premium.sh
set -euo pipefail

SRC="${SRC:-/home/togaa/ptsbi-premium}"
CONTAINER="${CONTAINER:-wordpress-project-coba}"
DEST="/var/www/html/wp-content/plugins/ptsbi-premium"

if [[ ! -d "$SRC" ]] && [[ -f /home/togaa/ptsbi-premium.zip ]]; then
  echo "Unzip /home/togaa/ptsbi-premium.zip ..."
  rm -rf "$SRC"
  unzip -q /home/togaa/ptsbi-premium.zip -d /home/togaa
  # Zip mungkin berisi folder ptsbi-premium — sudah benar
fi

if [[ ! -f "$SRC/ptsbi-premium.php" ]]; then
  echo "ERROR: Plugin tidak ditemukan di $SRC" >&2
  echo "Upload dulu folder ptsbi-premium atau ptsbi-premium.zip ke /home/togaa/" >&2
  exit 1
fi

docker ps --format '{{.Names}}' | grep -qx "$CONTAINER" || {
  echo "ERROR: Container $CONTAINER tidak jalan" >&2
  exit 1
}

echo "Copy ke $CONTAINER:$DEST ..."
docker exec "$CONTAINER" rm -rf "$DEST" 2>/dev/null || true
docker cp "$SRC" "$CONTAINER:$DEST"
docker exec "$CONTAINER" chown -R www-data:www-data "$DEST"
echo "BERHASIL — buka wp-admin → Plugins, pastikan Premium Organization aktif (v1.2.3)."
