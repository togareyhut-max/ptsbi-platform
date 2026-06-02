#!/usr/bin/env bash
# Deploy plugin ptsbi-premium ke container WordPress ptsbi.org
#
# Env wajib:
#   SSH_HOST, SSH_USER, WP_CONTAINER
# Opsional:
#   SSH_PORT (default 22)
#   REMOTE_STAGING (default /home/togaa/deploy-staging/ptsbi-premium)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SRC="${ROOT}/wordpress/ptsbi-premium"
SSH_PORT="${SSH_PORT:-22}"
REMOTE_STAGING="${REMOTE_STAGING:-/home/togaa/deploy-staging/ptsbi-premium}"
SSH_TARGET="${SSH_USER}@${SSH_HOST}"

if [[ -z "${WP_CONTAINER:-}" ]]; then
  echo "ERROR: set WP_CONTAINER (nama container WordPress ptsbi.org, cek: docker ps | grep wordpress)"
  exit 1
fi

if [[ ! -f "${SRC}/ptsbi-premium.php" ]]; then
  echo "ERROR: plugin tidak ditemukan: ${SRC}"
  exit 1
fi

echo "==> Rsync plugin ke staging server..."
ssh -p "${SSH_PORT}" -o StrictHostKeyChecking=accept-new "${SSH_TARGET}" "mkdir -p ${REMOTE_STAGING}"
rsync -avz --delete \
  -e "ssh -p ${SSH_PORT} -o StrictHostKeyChecking=accept-new" \
  "${SRC}/" "${SSH_TARGET}:${REMOTE_STAGING}/"

echo "==> Salin ke container ${WP_CONTAINER} ..."
ssh -p "${SSH_PORT}" -o StrictHostKeyChecking=accept-new "${SSH_TARGET}" bash -s <<EOF
set -euo pipefail
echo "==> Pastikan Tarombo hidup (self-heal) sebelum deploy plugin..."
if [[ -d /home/togaa/tarombo-app ]]; then
  cd /home/togaa/tarombo-app
  if [[ -f docker-compose.server.yml ]]; then
    docker compose -f docker-compose.server.yml up -d --build
    if [[ -f scripts/post-deploy-tarombo.sh ]]; then
      bash scripts/post-deploy-tarombo.sh || true
    fi
  fi
fi

docker exec "${WP_CONTAINER}" rm -rf /var/www/html/wp-content/plugins/ptsbi-premium 2>/dev/null || true
docker cp "${REMOTE_STAGING}" "${WP_CONTAINER}:/var/www/html/wp-content/plugins/ptsbi-premium"
docker exec "${WP_CONTAINER}" chown -R www-data:www-data /var/www/html/wp-content/plugins/ptsbi-premium
echo "Plugin terpasang. Buka wp-admin ptsbi.org → Plugins → pastikan Premium Organization aktif."
EOF

echo "Done."
