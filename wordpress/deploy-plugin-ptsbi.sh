#!/usr/bin/env bash
# Deploy ptsbi-premium ke server production (ptsbi.org).
# Butuh SSH sebagai user togaa@5.175.245.78
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_SRC="${ROOT}/wordpress/ptsbi-premium"
SSH_HOST="${PTPRM_SSH_HOST:-togaa@5.175.245.78}"
WP_CONTAINER="${PTPRM_WP_CONTAINER:-wordpress-ptsbi}"
REMOTE_DIR="/home/togaa/ptsbi-premium"
SSH_ID="${HOME}/.ssh/ptprm_deploy_key"

if [[ ! -f "${PLUGIN_SRC}/ptsbi-premium.php" ]]; then
  echo "Plugin tidak ditemukan: ${PLUGIN_SRC}" >&2
  exit 1
fi

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
    # Secret kadang disimpan satu baris; rapikan body base64 OpenSSH.
    if grep -q 'BEGIN OPENSSH PRIVATE KEY' "${SSH_ID}"; then
      python3 - "${SSH_ID}" <<'PY' || true
import sys, textwrap
path = sys.argv[1]
raw = open(path).read().strip()
header, footer = "-----BEGIN OPENSSH PRIVATE KEY-----", "-----END OPENSSH PRIVATE KEY-----"
if header not in raw or footer not in raw:
    sys.exit(1)
start, end = raw.find(header), raw.find(footer) + len(footer)
block = raw[start:end]
body = block.replace(header, "").replace(footer, "").strip()
fixed = f"{header}\n" + "\n".join(textwrap.wrap(body, 70)) + f"\n{footer}\n"
open(path, "w").write(fixed)
PY
    fi
  fi
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
  SCP_OPTS=(-P "${SSH_PORT}" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)
  if [[ -f "${SSH_ID}" ]]; then
    SCP_OPTS+=(-i "${SSH_ID}" -o IdentitiesOnly=yes)
  fi
  ssh "${SSH_OPTS[@]}" "${SSH_HOST}" "mkdir -p ${REMOTE_DIR}"
  scp "${SCP_OPTS[@]}" -r "${PLUGIN_SRC}/." "${SSH_HOST}:${REMOTE_DIR}/"
fi

echo "==> Salin ke container ${WP_CONTAINER} ..."
ssh "${SSH_OPTS[@]}" "${SSH_HOST}" bash -s <<EOF
set -euo pipefail
PLUGIN_PATH="/var/www/html/wp-content/plugins/ptsbi-premium"
docker exec ${WP_CONTAINER} rm -rf "\${PLUGIN_PATH}"
docker cp ${REMOTE_DIR} ${WP_CONTAINER}:\${PLUGIN_PATH}
docker exec ${WP_CONTAINER} chown -R www-data:www-data \${PLUGIN_PATH}
grep PTPRM_VERSION ${REMOTE_DIR}/ptsbi-premium.php | head -1
docker exec ${WP_CONTAINER} grep PTPRM_VERSION \${PLUGIN_PATH}/ptsbi-premium.php | head -1
EOF

echo "Selesai. Buka WP Admin atau Panel Pengurus → tab Dokumen PDF (v3.0.8)."
