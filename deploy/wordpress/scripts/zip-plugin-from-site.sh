#!/usr/bin/env bash
# ZIP plugin Bloggingpro (atau pola lain) dari kotakita
#   bash /home/togaa/zip-plugin-from-site.sh
set -u

CONTAINER="${CONTAINER:-wordpress-kotakita}"
SEARCH="${PLUGIN_SEARCH:-bloggingpro}"
PLUGINS="/var/www/html/wp-content/plugins"
OUT="${OUT:-$HOME/kotakita-plugin.zip}"

echo "=== ZIP plugin dari $CONTAINER ==="

if ! command -v zip >/dev/null 2>&1; then
  echo "Pasang zip dulu:"
  echo "  sudo apt install -y zip"
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
  echo "ERROR: container $CONTAINER tidak jalan"
  docker ps --format '{{.Names}}'
  exit 1
fi

echo ""
echo "1) Semua folder di plugins/:"
docker exec "$CONTAINER" ls -1 "$PLUGINS" 2>/dev/null | head -50 || echo "   (gagal baca plugins)"

echo ""
echo "2) Cari *${SEARCH}* ..."
SLUG=""
while IFS= read -r dir; do
  [[ -z "$dir" ]] && continue
  SLUG="$dir"
  break
done < <(docker exec "$CONTAINER" ls -1 "$PLUGINS" 2>/dev/null | grep -i "$SEARCH" || true)

# Cari di themes (kadang plugin dibundel di tema)
if [[ -z "$SLUG" ]]; then
  echo "   Tidak di plugins/ — cari di themes/..."
  docker exec "$CONTAINER" find /var/www/html/wp-content/themes -maxdepth 3 -type d -iname '*bloggingpro*' 2>/dev/null | head -10 || true
  THEME_PLUGIN=$(docker exec "$CONTAINER" find /var/www/html/wp-content/themes -maxdepth 4 -type f -iname '*bloggingpro*core*.php' 2>/dev/null | head -1 || true)
  if [[ -n "$THEME_PLUGIN" ]]; then
    echo "   Ditemukan file: $THEME_PLUGIN"
    echo "   Plugin mungkin bagian tema — zip folder tema sudah cukup, atau salin path di atas manual."
  fi
fi

if [[ -z "$SLUG" ]]; then
  echo ""
  echo "ERROR: tidak ada folder plugins/*${SEARCH}*"
  echo "Lihat daftar di atas, lalu jalankan:"
  echo "  PLUGIN_SLUG=nama-folder bash $0"
  exit 1
fi

echo "   Folder dipakai: $SLUG"
SRC="$PLUGINS/$SLUG"

docker exec "$CONTAINER" test -d "$SRC" || { echo "ERROR: $SRC tidak ada"; exit 1; }

TMP="$HOME/tmp-plugin-zip-$$"
rm -rf "$TMP"
mkdir -p "$TMP"

echo ""
echo "3) Salin dari container..."
if ! docker cp "$CONTAINER:$SRC" "$TMP/$SLUG"; then
  echo "ERROR: docker cp gagal (coba sudo atau login root)"
  exit 1
fi

echo "4) Buat zip..."
rm -f "$OUT"
if (cd "$TMP" && zip -qr "$OUT" "$SLUG"); then
  :
else
  echo "ERROR: zip gagal"
  exit 1
fi
rm -rf "$TMP"

if [[ ! -f "$OUT" ]]; then
  echo "ERROR: file tidak ada: $OUT"
  exit 1
fi

echo ""
echo "=== SELESAI ==="
ls -lh "$OUT"
echo ""
echo "Download WinSCP: $OUT"
echo "Upload: Plugins -> Add New -> Upload Plugin"
