#!/usr/bin/env bash
# Tarik plugin ptsbi-premium dari server production (sumber kebenaran = website live).
# Mengosongkan lalu menimpa folder lokal wordpress/ptsbi-premium di repo.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_DST="${ROOT}/wordpress/ptsbi-premium"
SSH_HOST="${PTPRM_SSH_HOST:-togaa@5.175.245.78}"
WP_CONTAINER="${PTPRM_WP_CONTAINER:-wordpress-ptsbi}"
WP_VOLUME="${PTPRM_WP_VOLUME:-wordpress_wordpress_ptsbi}"
REMOTE_SNAPSHOT="/home/togaa/ptsbi-premium-live-snapshot"
PLUGIN_IN_CONTAINER="/var/www/html/wp-content/plugins/ptsbi-premium"
SSH_ID="${HOME}/.ssh/ptprm_deploy_key"

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
  local host="${DEPLOY_HOST:-5.175.245.78}"
  local port="${PTPRM_SSH_PORT:-22}"
  ssh-keyscan -p "${port}" -H "${host}" >> "${HOME}/.ssh/known_hosts" 2>/dev/null || true
  return 0
}

SSH_PORT="${PTPRM_SSH_PORT:-22}"
SSH_OPTS=(-p "${SSH_PORT}" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)
if setup_ssh_key; then
  SSH_OPTS+=(-i "${SSH_ID}" -o IdentitiesOnly=yes)
  echo "==> Memakai kunci dari SSH_PRIVATE_KEY"
else
  echo "==> Peringatan: SSH_PRIVATE_KEY tidak diset; memakai kunci default ~/.ssh" >&2
fi

echo "==> Salin plugin live dari volume Docker ke ${SSH_HOST}:${REMOTE_SNAPSHOT} ..."
ssh "${SSH_OPTS[@]}" "${SSH_HOST}" bash -s <<EOF
set -euo pipefail
WP_CONTAINER="${WP_CONTAINER}"
WP_VOLUME="${WP_VOLUME}"
REMOTE_SNAPSHOT="${REMOTE_SNAPSHOT}"
PLUGIN_IN_CONTAINER="${PLUGIN_IN_CONTAINER}"

rm -rf "\${REMOTE_SNAPSHOT}"
mkdir -p "\${REMOTE_SNAPSHOT}"

if docker volume inspect "\${WP_VOLUME}" >/dev/null 2>&1; then
  docker run --rm \\
    -v "\${WP_VOLUME}:/vol:ro" \\
    -v "\${REMOTE_SNAPSHOT}:/out" \\
    alpine:3.20 \\
    sh -c 'rm -rf /out/* && cp -a /vol/wp-content/plugins/ptsbi-premium/. /out/'
else
  docker cp "\${WP_CONTAINER}:\${PLUGIN_IN_CONTAINER}/." "\${REMOTE_SNAPSHOT}/"
fi

test -f "\${REMOTE_SNAPSHOT}/ptsbi-premium.php"
grep 'PTPRM_VERSION' "\${REMOTE_SNAPSHOT}/ptsbi-premium.php" | head -1
EOF

echo "==> Kosongkan lokal lalu unduh ke repo: ${PLUGIN_DST} ..."
mkdir -p "${PLUGIN_DST}"
if command -v rsync >/dev/null 2>&1; then
  rsync -az --delete -e "ssh ${SSH_OPTS[*]}" "${SSH_HOST}:${REMOTE_SNAPSHOT}/" "${PLUGIN_DST}/"
else
  rm -rf "${PLUGIN_DST:?}"/*
  scp "${SSH_OPTS[@]}" -r "${SSH_HOST}:${REMOTE_SNAPSHOT}/." "${PLUGIN_DST}/"
fi

VERSION_LINE="$(grep 'PTPRM_VERSION' "${PLUGIN_DST}/ptsbi-premium.php" | head -1 || true)"
echo "==> Selesai. Plugin live di repo:"
echo "${VERSION_LINE:- (versi tidak terbaca)}"
echo "${PLUGIN_DST}"
