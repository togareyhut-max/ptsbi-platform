#!/usr/bin/env bash
# Deploy ptsbi-premium ke server production (ptsbi.org).
# Butuh SSH sebagai user togaa@5.175.245.78
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_SRC="${ROOT}/wordpress/ptsbi-premium"
SSH_HOST="${PTPRM_SSH_HOST:-togaa@5.175.245.78}"
WP_CONTAINER="${PTPRM_WP_CONTAINER:-wordpress-ptsbi}"
REMOTE_DIR="/home/togaa/ptsbi-premium"

if [[ ! -f "${PLUGIN_SRC}/ptsbi-premium.php" ]]; then
  echo "Plugin tidak ditemukan: ${PLUGIN_SRC}" >&2
  exit 1
fi

echo "==> Upload ke ${SSH_HOST}:${REMOTE_DIR} ..."
if command -v rsync >/dev/null 2>&1; then
  rsync -az --delete "${PLUGIN_SRC}/" "${SSH_HOST}:${REMOTE_DIR}/"
else
  ssh "${SSH_HOST}" "mkdir -p ${REMOTE_DIR}"
  scp -r "${PLUGIN_SRC}/." "${SSH_HOST}:${REMOTE_DIR}/"
fi

echo "==> Salin ke container ${WP_CONTAINER} ..."
ssh "${SSH_HOST}" bash -s <<EOF
set -euo pipefail
docker cp ${REMOTE_DIR} ${WP_CONTAINER}:/var/www/html/wp-content/plugins/ptsbi-premium
docker exec ${WP_CONTAINER} chown -R www-data:www-data /var/www/html/wp-content/plugins/ptsbi-premium
grep PTPRM_VERSION ${REMOTE_DIR}/ptsbi-premium.php | head -1
EOF

echo "Selesai. Buka WP Admin → Premium Plugin → tab Dokumen PDF."
