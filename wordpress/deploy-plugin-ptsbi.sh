#!/usr/bin/env bash
# Deploy ptsbi-premium ke server production (ptsbi.org).
# Butuh SSH sebagai user togaa@5.175.245.78
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_SRC="${ROOT}/wordpress/ptsbi-premium"
SSH_HOST="${PTPRM_SSH_HOST:-togaa@5.175.245.78}"
WP_CONTAINER="${PTPRM_WP_CONTAINER:-wordpress-ptsbi}"
REMOTE_DIR="/home/togaa/ptsbi-premium"
PLUGIN_SLUG="ptsbi-premium"
SSH_ID="${HOME}/.ssh/ptprm_deploy_key"
DEPLOY_HOST="${DEPLOY_HOST:-5.175.245.78}"

if [[ ! -f "${PLUGIN_SRC}/ptsbi-premium.php" ]]; then
  echo "Plugin tidak ditemukan: ${PLUGIN_SRC}" >&2
  exit 1
fi

LOCAL_VER="$(grep -m1 "define( 'PTPRM_VERSION'" "${PLUGIN_SRC}/ptsbi-premium.php" | sed -E "s/.*'([0-9.]+)'.*/\1/")"
echo "==> Versi sumber deploy: ${LOCAL_VER}"

setup_ssh_key() {
  if [[ -z "${SSH_PRIVATE_KEY:-}" ]]; then
    return 1
  fi
  mkdir -p "${HOME}/.ssh"
  chmod 700 "${HOME}/.ssh"
  if echo "${SSH_PRIVATE_KEY}" | base64 -d > "${SSH_ID}" 2>/dev/null; then
    :
  else
    printf '%s\n' "${SSH_PRIVATE_KEY}" > "${SSH_ID}"
  fi
  chmod 600 "${SSH_ID}"
  if ! ssh-keygen -y -f "${SSH_ID}" >/dev/null 2>&1; then
    echo "SSH_PRIVATE_KEY tidak valid (harus isi file privat PEM atau base64-nya)." >&2
    rm -f "${SSH_ID}"
    return 1
  fi
  local port="${PTPRM_SSH_PORT:-22}"
  ssh-keygen -f "${HOME}/.ssh/known_hosts" -R "${DEPLOY_HOST}" 2>/dev/null || true
  ssh-keyscan -p "${port}" -H "${DEPLOY_HOST}" >> "${HOME}/.ssh/known_hosts" 2>/dev/null || true
  return 0
}

SSH_PORT="${PTPRM_SSH_PORT:-22}"
SSH_OPTS=(-p "${SSH_PORT}" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)
if setup_ssh_key; then
  SSH_OPTS+=(-i "${SSH_ID}" -o IdentitiesOnly=yes)
  echo "==> Memakai kunci dari secret SSH_PRIVATE_KEY"
else
  echo "==> Peringatan: SSH_PRIVATE_KEY tidak diset; memakai kunci default ~/.ssh" >&2
  ssh-keygen -f "${HOME}/.ssh/known_hosts" -R "${DEPLOY_HOST}" 2>/dev/null || true
  ssh-keyscan -p "${SSH_PORT}" -H "${DEPLOY_HOST}" >> "${HOME}/.ssh/known_hosts" 2>/dev/null || true
fi

echo "==> Upload ke ${SSH_HOST}:${REMOTE_DIR} ..."
if command -v rsync >/dev/null 2>&1; then
  rsync -az --delete -e "ssh ${SSH_OPTS[*]}" "${PLUGIN_SRC}/" "${SSH_HOST}:${REMOTE_DIR}/"
else
  ssh "${SSH_OPTS[@]}" "${SSH_HOST}" "mkdir -p ${REMOTE_DIR}"
  scp "${SSH_OPTS[@]}" -r "${PLUGIN_SRC}/." "${SSH_HOST}:${REMOTE_DIR}/"
fi

echo "==> Salin ke container ${WP_CONTAINER}, aktifkan plugin, flush cache ..."
ssh "${SSH_OPTS[@]}" "${SSH_HOST}" bash -s <<EOF
set -euo pipefail
PLUGIN_PATH="/var/www/html/wp-content/plugins/${PLUGIN_SLUG}"
docker exec ${WP_CONTAINER} rm -rf "\${PLUGIN_PATH}"
docker exec ${WP_CONTAINER} mkdir -p "\${PLUGIN_PATH}"
docker cp ${REMOTE_DIR}/. ${WP_CONTAINER}:"\${PLUGIN_PATH}/"
docker exec ${WP_CONTAINER} chown -R www-data:www-data "\${PLUGIN_PATH}"
docker exec ${WP_CONTAINER} grep -m1 "PTPRM_VERSION" "\${PLUGIN_PATH}/ptsbi-premium.php" || true
if docker exec ${WP_CONTAINER} which wp >/dev/null 2>&1; then
  docker exec ${WP_CONTAINER} wp plugin activate ${PLUGIN_SLUG} --allow-root 2>/dev/null || true
  docker exec ${WP_CONTAINER} wp cache flush --allow-root 2>/dev/null || true
  docker exec ${WP_CONTAINER} wp plugin is-active ${PLUGIN_SLUG} --allow-root 2>/dev/null && echo "PLUGIN_ACTIVE=yes" || echo "PLUGIN_ACTIVE=no"
else
  echo "WP-CLI tidak ada di container — pastikan plugin Premium Organization aktif di wp-admin."
fi
for c in ${WP_CONTAINER} traefik tarombo-web mysql; do
  if docker ps --format '{{.Names}}' | grep -qx "\$c"; then
    echo "CONTAINER_UP=\$c"
  else
    echo "CONTAINER_DOWN=\$c"
    case "\$c" in
      ${WP_CONTAINER})
        cd /home/togaa/wordpress 2>/dev/null && docker compose up -d wordpress-ptsbi 2>/dev/null || true
        ;;
      traefik)
        cd /home/togaa/traefik 2>/dev/null && docker compose up -d 2>/dev/null || true
        ;;
      tarombo-web)
        cd /home/togaa/tarombo-app 2>/dev/null && docker compose up -d 2>/dev/null || true
        ;;
      mysql)
        cd /home/togaa/wordpress 2>/dev/null && docker compose up -d mysql 2>/dev/null || true
        ;;
    esac
  fi
done
EOF

echo "==> Selesai deploy v${LOCAL_VER} — cek PLUGIN_ACTIVE dan CONTAINER_* di atas."
