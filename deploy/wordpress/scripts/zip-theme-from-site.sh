#!/usr/bin/env bash
# ZIP tema aktif dari site WordPress (default kotakita)
#   bash /home/togaa/zip-theme-from-site.sh
# Hasil: ~/kotakita-theme-export.zip  (bisa di-download WinSCP)
set -euo pipefail

CONTAINER="${CONTAINER:-wordpress-kotakita}"
OUT="${OUT:-$HOME/kotakita-theme-export.zip}"
STAGING="/tmp/theme-export-$$"
THEMES_ROOT="/var/www/html/wp-content/themes"

fail() { echo ""; echo "ERROR: $*" >&2; exit 1; }

command -v docker >/dev/null || fail "docker tidak ada"
command -v zip >/dev/null || fail "zip belum ada — jalankan: sudo apt install -y zip"

echo "==> Cek container $CONTAINER..."
docker ps --format '{{.Names}}' | grep -qx "$CONTAINER" || fail "Container $CONTAINER tidak jalan. Cek: docker ps"

echo "==> Baca tema aktif dari database..."
DB_NAME=$(docker exec "$CONTAINER" sh -c "grep DB_NAME /var/www/html/wp-config.php | head -1" | sed -E "s/.*['\"]([^'\"]+)['\"].*/\1/")
[[ -n "$DB_NAME" ]] || fail "DB_NAME tidak terbaca dari wp-config"

MYSQL_ROOT=$(docker exec mysql printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true)
[[ -n "$MYSQL_ROOT" ]] || fail "MYSQL_ROOT tidak terbaca dari container mysql"

PREFIX=$(docker exec "$CONTAINER" sh -c "grep table_prefix /var/www/html/wp-config.php | head -1" 2>/dev/null | sed -E "s/.*['\"]([^'\"]+)['\"].*/\1/" || echo "wp_")
[[ "$PREFIX" == *'$'* ]] && PREFIX="wp_"

TEMPLATE=$(docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -N -uroot "$DB_NAME" -e \
  "SELECT option_value FROM ${PREFIX}options WHERE option_name='template' LIMIT 1;" 2>/dev/null | tr -d '\r')
STYLESHEET=$(docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -N -uroot "$DB_NAME" -e \
  "SELECT option_value FROM ${PREFIX}options WHERE option_name='stylesheet' LIMIT 1;" 2>/dev/null | tr -d '\r')

if [[ -z "$TEMPLATE" ]]; then
  echo "    DB kosong — daftar folder themes:"
  docker exec "$CONTAINER" ls -1 "$THEMES_ROOT" 2>/dev/null || true
  fail "Tema aktif tidak ketemu di database $DB_NAME"
fi

echo "    Parent:  $TEMPLATE"
echo "    Aktif:   ${STYLESHEET:-$TEMPLATE}"

rm -rf "$STAGING"
mkdir -p "$STAGING/themes"

copy_theme() {
  local name=$1
  [[ -z "$name" ]] && return 0
  if docker exec "$CONTAINER" test -d "$THEMES_ROOT/$name"; then
    echo "    Salin: themes/$name"
    docker cp "$CONTAINER:$THEMES_ROOT/$name" "$STAGING/themes/$name"
  else
    echo "    SKIP (tidak ada): $name"
  fi
}

copy_theme "$TEMPLATE"
[[ -n "$STYLESHEET" && "$STYLESHEET" != "$TEMPLATE" ]] && copy_theme "$STYLESHEET"

# ZIP per tema (WordPress upload butuh 1 folder tema per zip — buat 2 file jika perlu)
MAIN_ZIP="${OUT%.zip}-${TEMPLATE}.zip"
rm -f "$OUT" "$MAIN_ZIP" 2>/dev/null || true

if [[ -d "$STAGING/themes/$TEMPLATE" ]]; then
  (cd "$STAGING/themes" && zip -r "$MAIN_ZIP" "$TEMPLATE")
  echo ""
  echo "ZIP tema parent: $MAIN_ZIP"
  ls -lh "$MAIN_ZIP"
fi

if [[ -n "$STYLESHEET" && "$STYLESHEET" != "$TEMPLATE" && -d "$STAGING/themes/$STYLESHEET" ]]; then
  CHILD_ZIP="${OUT%.zip}-${STYLESHEET}.zip"
  (cd "$STAGING/themes" && zip -r "$CHILD_ZIP" "$STYLESHEET")
  echo "ZIP child theme: $CHILD_ZIP"
  ls -lh "$CHILD_ZIP"
fi

# Arsip lengkap (backup)
(cd "$STAGING" && zip -r "$OUT" .)
rm -rf "$STAGING"

echo ""
echo "=============================================="
echo " SELESAI"
echo "=============================================="
ls -lh "$OUT" "${OUT%.zip}"-*.zip 2>/dev/null || ls -lh "$OUT"
echo ""
echo "Download lewat WinSCP (jangan ketik path di bash):"
echo "  $MAIN_ZIP"
echo "  (dan child zip jika ada)"
echo ""
echo "Upload di site lain:"
echo "  Appearance -> Themes -> Add New -> Upload Theme"
echo "=============================================="
