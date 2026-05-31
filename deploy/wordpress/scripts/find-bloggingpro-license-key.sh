#!/usr/bin/env bash
# Cari license key format: AAAAA_bbbbbbbbbbbbbbb (5 besar + _ + 15 kecil)
# Hasil: ~/kotakita-license-key.txt  — rahasiakan
set -u

DB_NAME="${DB_NAME:-wp_kotakita}"
REPORT="${REPORT:-$HOME/kotakita-license-key.txt}"
PATTERN='[A-Z]{5}_[a-z]{15}'

MYSQL_ROOT=$(docker exec mysql printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true)
[[ -n "$MYSQL_ROOT" ]] || { echo "ERROR: MYSQL_ROOT"; exit 1; }

mysql_q() {
  docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -uroot "$DB_NAME" -N -e "$1" 2>/dev/null
}

{
  echo "Pencarian license — $(date)"
  echo "Pola: 5 HURUF BESAR + _ + 15 huruf kecil"
  echo "========================================"
  echo ""

  echo "=== wp_options (cocok pola) ==="
  mysql_q "
SELECT option_name, option_value
FROM wp_options
WHERE option_value REGEXP '${PATTERN}'
   OR option_value REGEXP '${PATTERN//_/\\\\_}'
LIMIT 50;
"

  echo ""
  echo "=== wp_options (nama mengandung license / blogging / theme) ==="
  mysql_q "
SELECT option_name, LEFT(option_value, 200)
FROM wp_options
WHERE option_name REGEXP 'license|licence|blogging|purchase|activation|envato|edd'
ORDER BY option_name;
"

  echo ""
  echo "=== Cari di SEMUA tabel wp_kotakita (lambat, ~1 menit) ==="
  TABLES=$(mysql_q "SELECT table_name FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name LIKE 'wp_%';")
  for t in $TABLES; do
    cols=$(mysql_q "SELECT column_name FROM information_schema.columns WHERE table_schema='${DB_NAME}' AND table_name='${t}' AND data_type IN ('varchar','text','mediumtext','longtext');")
    for c in $cols; do
      hit=$(mysql_q "SELECT COUNT(*) FROM \`${t}\` WHERE \`${c}\` REGEXP '${PATTERN}';" 2>/dev/null || echo 0)
      if [[ "${hit:-0}" != "0" && "${hit:-0}" != "" ]]; then
        echo "--- $t.$c ($hit baris) ---"
        mysql_q "SELECT \`${c}\` FROM \`${t}\` WHERE \`${c}\` REGEXP '${PATTERN}' LIMIT 3;"
      fi
    done
  done

  echo ""
  echo "=== wp-config / plugin (grep) ==="
  docker exec wordpress-kotakita grep -rE '[A-Z]{5}_[a-z]{15}' /var/www/html/wp-config.php /var/www/html/wp-content/plugins/bloggingpro-core 2>/dev/null | head -20 || true

  echo ""
  echo "Jika kosong: key mungkin belum pernah disimpan, atau di akun email pembelian / ThemeForest."
} | tee "$REPORT"

chmod 600 "$REPORT" 2>/dev/null || true
echo ""
echo "File: $REPORT"
echo "Tampilkan: grep -E '[A-Z]{5}_[a-z]{15}' $REPORT"
