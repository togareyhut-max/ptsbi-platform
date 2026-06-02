#!/usr/bin/env bash
# Deploy ptsbi-premium ke server production (ptsbi.org).
# Plugin WordPress disimpan di volume Docker (bukan layer container) — salin lewat volume.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_SRC="${ROOT}/wordpress/ptsbi-premium"
SSH_HOST="${PTPRM_SSH_HOST:-togaa@5.175.245.78}"
WP_CONTAINER="${PTPRM_WP_CONTAINER:-wordpress-ptsbi}"
WP_VOLUME="${PTPRM_WP_VOLUME:-wordpress_wordpress_ptsbi}"
REMOTE_DIR="/home/togaa/ptsbi-premium"
REMOTE_ROLLBACK="/home/togaa/ptsbi-premium-rollback"
PLUGIN_IN_CONTAINER="/var/www/html/wp-content/plugins/ptsbi-premium"
SSH_ID="${HOME}/.ssh/ptprm_deploy_key"

if [[ ! -f "${PLUGIN_SRC}/ptsbi-premium.php" ]]; then
  echo "Plugin tidak ditemukan: ${PLUGIN_SRC}" >&2
  exit 1
fi

EXPECTED_VERSION="$(grep -oP "define\(\s*'PTPRM_VERSION',\s*'\K[^']+" "${PLUGIN_SRC}/ptsbi-premium.php" | head -1 || true)"
if [[ -z "${EXPECTED_VERSION}" ]]; then
  echo "PTPRM_VERSION tidak terbaca di ${PLUGIN_SRC}/ptsbi-premium.php" >&2
  exit 1
fi
echo "==> Versi sumber deploy: ${EXPECTED_VERSION}"

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
  echo "==> Memakai kunci dari secret SSH_PRIVATE_KEY"
else
  echo "==> Peringatan: SSH_PRIVATE_KEY tidak diset; memakai kunci default ~/.ssh" >&2
fi

echo "==> Upload ke ${SSH_HOST}:${REMOTE_DIR} ..."
if command -v rsync >/dev/null 2>&1; then
  rsync -az --delete -e "ssh ${SSH_OPTS[*]}" "${PLUGIN_SRC}/" "${SSH_HOST}:${REMOTE_DIR}/"
else
  ssh "${SSH_OPTS[@]}" "${SSH_HOST}" "mkdir -p ${REMOTE_DIR}"
  scp "${SSH_OPTS[@]}" -r "${PLUGIN_SRC}/." "${SSH_HOST}:${REMOTE_DIR}/"
fi

echo "==> Salin ke volume ${WP_VOLUME} (container ${WP_CONTAINER}) ..."
ssh "${SSH_OPTS[@]}" "${SSH_HOST}" bash -s <<EOF
set -euo pipefail
REMOTE_DIR="${REMOTE_DIR}"
REMOTE_ROLLBACK="${REMOTE_ROLLBACK}"
WP_CONTAINER="${WP_CONTAINER}"
WP_VOLUME="${WP_VOLUME}"
PLUGIN_IN_CONTAINER="${PLUGIN_IN_CONTAINER}"
EXPECTED_VERSION="${EXPECTED_VERSION}"

if ! docker volume inspect "\${WP_VOLUME}" >/dev/null 2>&1; then
  DETECTED="\$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html"}}{{.Name}}{{end}}{{end}}' "\${WP_CONTAINER}" 2>/dev/null || true)"
  if [[ -n "\${DETECTED}" ]] && docker volume inspect "\${DETECTED}" >/dev/null 2>&1; then
    echo "Volume \${WP_VOLUME} tidak ada; memakai volume dari container: \${DETECTED}"
    WP_VOLUME="\${DETECTED}"
  else
    echo "Volume \${WP_VOLUME} tidak ditemukan dan deteksi otomatis gagal." >&2
    exit 1
  fi
fi

rollback_restore() {
  if [[ ! -d "\${REMOTE_ROLLBACK}/ptsbi-premium" ]]; then
    echo "Rollback tidak tersedia (cadangan kosong)." >&2
    return 1
  fi
  echo "==> ROLLBACK: mengembalikan plugin dari cadangan ..."
  docker run --rm \\
    -v "\${WP_VOLUME}:/html" \\
    -v "\${REMOTE_ROLLBACK}:/backup:ro" \\
    alpine:3.20 sh -c 'rm -rf /html/wp-content/plugins/ptsbi-premium && cp -a /backup/ptsbi-premium /html/wp-content/plugins/ptsbi-premium && chown -R 33:33 /html/wp-content/plugins/ptsbi-premium'
  docker exec "\${WP_CONTAINER}" grep PTPRM_VERSION "\${PLUGIN_IN_CONTAINER}/ptsbi-premium.php" | head -1 || true
}

trap 'if [[ \$? -ne 0 ]]; then rollback_restore || true; fi' ERR

echo "==> Cadangan plugin live ke \${REMOTE_ROLLBACK} ..."
docker run --rm \\
  -v /home/togaa:/togaa \\
  alpine:3.20 sh -c 'rm -rf /togaa/ptsbi-premium-rollback && mkdir -p /togaa/ptsbi-premium-rollback'
docker run --rm \\
  -v "\${WP_VOLUME}:/html:ro" \\
  -v "\${REMOTE_ROLLBACK}:/backup" \\
  alpine:3.20 sh -c 'cp -a /html/wp-content/plugins/ptsbi-premium /backup/ptsbi-premium'

echo "==> Deploy ke volume ..."
docker run --rm \\
  -v "\${WP_VOLUME}:/html" \\
  -v "\${REMOTE_DIR}:/src:ro" \\
  alpine:3.20 sh -c 'rm -rf /html/wp-content/plugins/ptsbi-premium && cp -a /src /html/wp-content/plugins/ptsbi-premium && chown -R 33:33 /html/wp-content/plugins/ptsbi-premium'

LIVE_VERSION="\$(docker exec "\${WP_CONTAINER}" grep -oP "define\\(\\s*'PTPRM_VERSION',\\s*'\\K[^']+" "\${PLUGIN_IN_CONTAINER}/ptsbi-premium.php" | head -1 || true)"
echo "==> Versi di staging host:"
grep PTPRM_VERSION "\${REMOTE_DIR}/ptsbi-premium.php" | head -1
echo "==> Versi di container (volume): \${LIVE_VERSION:- (gagal baca)}"

if [[ "\${LIVE_VERSION}" != "\${EXPECTED_VERSION}" ]]; then
  echo "Deploy GAGAL: versi live (\${LIVE_VERSION:-?}) != sumber (\${EXPECTED_VERSION})" >&2
  rollback_restore
  exit 1
fi

docker exec "\${WP_CONTAINER}" php -r 'if (function_exists("opcache_reset")) { opcache_reset(); }' 2>/dev/null || true
docker exec "\${WP_CONTAINER}" wp cache flush --allow-root 2>/dev/null || true
docker exec "\${WP_CONTAINER}" wp litespeed-purge all --allow-root 2>/dev/null || true

trap - ERR
echo "Deploy berhasil — versi \${EXPECTED_VERSION} aktif di volume."
EOF

echo "Selesai. Plugin ptsbi-premium ${EXPECTED_VERSION} terpasang di production."
