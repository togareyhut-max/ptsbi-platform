#!/usr/bin/env bash
# Cari data lisensi tema/plugin di kotakita.net (hanya di server Anda)
#   bash /home/togaa/find-license-kotakita.sh
# Hasil: /home/togaa/kotakita-license-report.txt  (JANGAN share file ini ke publik)
set -u

CONTAINER="${CONTAINER:-wordpress-kotakita}"
REPORT="${REPORT:-$HOME/kotakita-license-report.txt}"
DB_NAME="${DB_NAME:-wp_kotakita}"

MYSQL_ROOT=$(docker exec mysql printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true)
[[ -n "$MYSQL_ROOT" ]] || { echo "ERROR: MYSQL_ROOT tidak terbaca"; exit 1; }

PREFIX=$(docker exec "$CONTAINER" sh -c "grep table_prefix /var/www/html/wp-config.php | head -1" 2>/dev/null | sed -E "s/.*['\"]([^'\"]+)['\"].*/\1/" || echo "wp_")
[[ "$PREFIX" == *'$'* ]] && PREFIX="wp_"

{
  echo "Laporan lisensi — $(date)"
  echo "Site: kotakita.net | DB: $DB_NAME"
  echo "========================================"
  echo ""
  echo "=== wp_options (nama mengandung license / bloggingpro / edd) ==="
  docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -uroot "$DB_NAME" -e "
SELECT option_name, LEFT(option_value, 120) AS value_preview
FROM ${PREFIX}options
WHERE option_name REGEXP 'license|licence|bloggingpro|edd_|activate|purchase|envato|market'
   OR option_name LIKE '%_key'
ORDER BY option_name;
" 2>/dev/null || echo "(query gagal)"

  echo ""
  echo "=== wp-config.php (define license/key) ==="
  docker exec "$CONTAINER" grep -iE 'license|licence|key|bloggingpro|envato' /var/www/html/wp-config.php 2>/dev/null || echo "(tidak ada)"

  echo ""
  echo "=== File plugin bloggingpro-core (string license) ==="
  docker exec "$CONTAINER" grep -riE 'license|licence|edd_|api_key|activation' \
    /var/www/html/wp-content/plugins/bloggingpro-core 2>/dev/null | grep -v '.png' | head -40 || echo "(tidak ada / folder tidak ada)"

  echo ""
  echo "=== Tema aktif — opsi license ==="
  TEMPLATE=$(docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -N -uroot "$DB_NAME" -e \
    "SELECT option_value FROM ${PREFIX}options WHERE option_name='stylesheet' LIMIT 1;" 2>/dev/null | tr -d '\r')
  if [[ -n "$TEMPLATE" ]]; then
    docker exec "$CONTAINER" grep -riE 'license|licence' \
      "/var/www/html/wp-content/themes/$TEMPLATE" 2>/dev/null | head -20 || true
  fi

  echo ""
  echo "=== Petunjuk wp-admin (manual) ==="
  echo "1. Settings -> tema Bloggingpro / Theme Options -> tab License"
  echo "2. Plugins -> Bloggingpro Core -> Settings / License"
  echo "3. Appearance -> Theme License (jika ada)"
  echo ""
  echo "Pindah ke site lain: gunakan KEY yang sama di halaman yang sama, lalu Activate."
  echo "Cek kuota site di akun vendor (ThemeForest / vendor Bloggingpro)."
} | tee "$REPORT"

chmod 600 "$REPORT" 2>/dev/null || true
echo ""
echo "Selesai: $REPORT"
echo "Buka: cat $REPORT"
echo "PRIVASI: jangan kirim isi file ke chat/email publik."
