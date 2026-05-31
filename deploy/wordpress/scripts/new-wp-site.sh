#!/usr/bin/env bash
# new-wp-site.sh — cetak variabel + SQL untuk situs WordPress baru (pola ptsbi/lpmpjk)
# Usage: ./new-wp-site.sh SLUG DOMAIN DB_PASSWORD
# Example: ./new-wp-site.sh kotakita kotakita.net 'MyStr0ngPass!'

set -euo pipefail

SLUG="${1:-}"
DOMAIN="${2:-}"
DB_PASS="${3:-}"

if [[ -z "$SLUG" || -z "$DOMAIN" || -z "$DB_PASS" ]]; then
  echo "Usage: $0 SLUG DOMAIN DB_PASSWORD"
  echo "Example: $0 kotakita kotakita.net 'MyStr0ngPass!'"
  exit 1
fi

echo "=============================================="
echo " WordPress site baru — pola vm21197"
echo "=============================================="
echo "Service:     wordpress-${SLUG}"
echo "Container:   wordpress-${SLUG}"
echo "Volume:      wordpress_${SLUG}"
echo "Database:    wp_${SLUG}"
echo "DB user:     ${SLUG}"
echo "Domain:      ${DOMAIN} (+ www)"
echo "IPv4 DNS:    5.175.245.78"
echo ""
echo "--- SQL (jalankan: docker exec -it mysql mysql -uroot -p) ---"
cat <<SQL
CREATE DATABASE wp_${SLUG} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${SLUG}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL ON wp_${SLUG}.* TO '${SLUG}'@'%';
FLUSH PRIVILEGES;
SQL
echo ""
echo "--- Langkah berikutnya ---"
echo "1. Salin blok dari templates/wordpress-service.yml.example ke"
echo "   /home/togaa/wordpress/docker-compose.yml"
echo "2. cd /home/togaa/wordpress && docker compose up -d wordpress-${SLUG}"
echo "3. Restore backup atau buka https://${DOMAIN}/"
echo "4. DNS: A @ dan www -> 5.175.245.78"
echo "=============================================="
