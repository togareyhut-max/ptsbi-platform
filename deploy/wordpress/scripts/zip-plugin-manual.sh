#!/usr/bin/env bash
# ZIP plugin — ketik NAMA FOLDER persis (dari: docker exec wordpress-kotakita ls .../plugins)
#   bash zip-plugin-manual.sh bloggingpro-core
set -u
SLUG="${1:-}"
CONTAINER="${CONTAINER:-wordpress-kotakita}"
[[ -n "$SLUG" ]] || { echo "Usage: bash $0 NAMA_FOLDER_PLUGIN"; exit 1; }
OUT="$HOME/plugin-${SLUG}.zip"
PLUGINS="/var/www/html/wp-content/plugins"
TMP="$HOME/tmp-zip-$$"
rm -rf "$TMP" && mkdir -p "$TMP"
docker cp "$CONTAINER:$PLUGINS/$SLUG" "$TMP/$SLUG"
(cd "$TMP" && zip -qr "$OUT" "$SLUG")
rm -rf "$TMP"
ls -lh "$OUT"
echo "WinSCP: $OUT"
