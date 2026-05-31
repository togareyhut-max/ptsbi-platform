#!/usr/bin/env bash
# Hapus total plugin Premium Organization / ptsbi-premium dari SEMUA container WordPress.
# Jalankan di server (SSH): bash cleanup-ptsbi-premium-plugin.sh
#
# Opsional:
#   CONTAINER=wordpress-ptsbi bash cleanup-ptsbi-premium-plugin.sh   # satu site saja
set -euo pipefail

NAMES=(
  ptsbi-premium
  ptsbi-premium-1
  ptsbi-premium-2
  ptsbi-premium-main
  premium-organization
  Premium-Organization
  premium_organization
  ptsbi-premium-org
  ptsbi-permium
)

pick_containers() {
  if [[ -n "${CONTAINER:-}" ]]; then
    echo "$CONTAINER"
    return
  fi
  docker ps --format '{{.Names}}' | grep -E '^wordpress-' || true
}

echo "=== Container WordPress ==="
mapfile -t CONTAINERS < <(pick_containers)
if [[ ${#CONTAINERS[@]} -eq 0 ]]; then
  echo "Tidak ada container wordpress-* yang jalan." >&2
  exit 1
fi
printf '  %s\n' "${CONTAINERS[@]}"

for c in "${CONTAINERS[@]}"; do
  echo ""
  echo "=== $c ==="
  for n in "${NAMES[@]}"; do
    docker exec "$c" rm -rf "/var/www/html/wp-content/plugins/$n" 2>/dev/null || true
  done
  docker exec "$c" rm -rf /var/www/html/wp-content/upgrade 2>/dev/null || true
  docker exec "$c" rm -rf /var/www/html/wp-content/upgrade-temp-backup 2>/dev/null || true

  echo "Sisa di plugins (grep ptsbi/premium):"
  docker exec "$c" sh -c 'ls -la /var/www/html/wp-content/plugins/ 2>/dev/null' | grep -iE 'ptsbi|premium|organization' || echo "  (kosong)"

  echo "Find sisa:"
  docker exec "$c" find /var/www/html/wp-content -maxdepth 3 \( -iname '*ptsbi-premium*' -o -iname '*premium-organization*' -o -iname '*ptsbi-permium*' \) 2>/dev/null || true
done

echo ""
echo "=== Host /home/togaa ==="
for p in \
  /home/togaa/ptsbi-premium \
  /home/togaa/premium-organization \
  /home/togaa/ptsbi-premium.zip \
  /home/togaa/premium-organization.zip
do
  if [[ -e "$p" ]]; then
    echo "Hapus: $p"
    rm -rf "$p"
  fi
done
find /home/togaa -maxdepth 3 -type d \( -name 'ptsbi-premium' -o -name 'premium-organization' \) 2>/dev/null | while read -r d; do
  echo "Hapus: $d"
  rm -rf "$d"
done

echo ""
echo "SELESAI — folder plugin di container + home dibersihkan."
echo "Pasang ulang: docker cp /home/togaa/ptsbi-premium CONTAINER:/var/www/html/wp-content/plugins/ptsbi-premium"
