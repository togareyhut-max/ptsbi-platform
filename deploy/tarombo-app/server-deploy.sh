#!/usr/bin/env bash
# Deploy / inspeksi aplikasi Tarombo (tarombo.ptsbi.org) tanpa merusak data.
#
#   TAROMBO_MODE=inspect  -> hanya membaca kondisi server (read-only, default)
#   TAROMBO_MODE=deploy   -> sinkron kode terbaru + rebuild HANYA container web
#
# Data: volume database (pgdata) dan .env di server TIDAK disentuh.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
APP_SRC="${ROOT}/deploy/tarombo-app"
SSH_HOST="${PTPRM_SSH_HOST:-togaa@5.175.245.78}"
WEB_CONTAINER="${TAROMBO_WEB_CONTAINER:-tarombo-web}"
REMOTE_DIR="${TAROMBO_REMOTE_DIR:-/home/togaa/tarombo-app}"
SSH_ID="${HOME}/.ssh/ptprm_deploy_key"
DEPLOY_HOST="${DEPLOY_HOST:-5.175.245.78}"
MODE="${TAROMBO_MODE:-inspect}"

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
    echo "SSH_PRIVATE_KEY tidak valid." >&2
    rm -f "${SSH_ID}"
    return 1
  fi
  ssh-keygen -f "${HOME}/.ssh/known_hosts" -R "${DEPLOY_HOST}" 2>/dev/null || true
  ssh-keyscan -p "${PTPRM_SSH_PORT:-22}" -H "${DEPLOY_HOST}" >> "${HOME}/.ssh/known_hosts" 2>/dev/null || true
  return 0
}

SSH_PORT="${PTPRM_SSH_PORT:-22}"
SSH_OPTS=(-p "${SSH_PORT}" -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=15 -o ServerAliveCountMax=8)
if setup_ssh_key; then
  SSH_OPTS+=(-i "${SSH_ID}" -o IdentitiesOnly=yes)
  echo "==> Memakai kunci dari secret SSH_PRIVATE_KEY"
else
  echo "==> Peringatan: SSH_PRIVATE_KEY tidak diset; memakai kunci default ~/.ssh" >&2
  ssh-keygen -f "${HOME}/.ssh/known_hosts" -R "${DEPLOY_HOST}" 2>/dev/null || true
  ssh-keyscan -p "${SSH_PORT}" -H "${DEPLOY_HOST}" >> "${HOME}/.ssh/known_hosts" 2>/dev/null || true
fi

echo "==> MODE=${MODE}"

# --- INSPEKSI (read-only) ---
inspect_remote() {
  ssh "${SSH_OPTS[@]}" "${SSH_HOST}" bash -s <<REMOTE
set +e
echo "== Containers =="
docker ps -a --format '{{.Names}}\t{{.Image}}\t{{.Status}}'
echo
echo "== ${WEB_CONTAINER} compose metadata =="
docker inspect ${WEB_CONTAINER} --format 'project={{index .Config.Labels "com.docker.compose.project"}}
working_dir={{index .Config.Labels "com.docker.compose.project.working_dir"}}
config_files={{index .Config.Labels "com.docker.compose.project.config_files"}}
service={{index .Config.Labels "com.docker.compose.service"}}' 2>/dev/null || echo "(tidak ada container ${WEB_CONTAINER})"
echo
echo "== ${WEB_CONTAINER} DB env =="
docker inspect ${WEB_CONTAINER} --format '{{range .Config.Env}}{{println .}}{{end}}' 2>/dev/null | grep -iE 'DATABASE_URL|USE_SQLITE|POSTGRES|MYSQL|INTEGRATION_KEY' | sed -E 's/(KEY|PASSWORD)=.*/\1=***/I'
echo
echo "== ${WEB_CONTAINER} mounts =="
docker inspect ${WEB_CONTAINER} --format '{{range .Mounts}}{{.Type}} {{.Name}}{{.Source}} -> {{.Destination}}{{println}}{{end}}' 2>/dev/null
echo "== ${WEB_CONTAINER} networks =="
docker inspect ${WEB_CONTAINER} --format '{{range \$k,\$v := .NetworkSettings.Networks}}{{println \$k}}{{end}}' 2>/dev/null
echo
echo "== App dir ${REMOTE_DIR} =="
ls -la "${REMOTE_DIR}" 2>/dev/null | head -50
echo "== Compose files =="
ls "${REMOTE_DIR}"/*.yml 2>/dev/null
echo "== .env keys (nilai disembunyikan) =="
sed -E 's/=.*/=***/' "${REMOTE_DIR}/.env" 2>/dev/null || echo "(tidak ada .env)"
echo "== membership_api routes di server =="
grep -nE '@bp.route' "${REMOTE_DIR}/services/membership_api.py" 2>/dev/null || echo "(membership_api.py tidak ada / tanpa route)"
echo "== Uji dari DALAM container web (localhost:5000) =="
docker exec ${WEB_CONTAINER} python3 -c "import urllib.request;\nimport sys\ntry:\n  r=urllib.request.urlopen('http://127.0.0.1:5000/',timeout=8);print('web localhost ->',r.status)\nexcept Exception as e:\n  print('web localhost ERROR:',e)" 2>/dev/null || echo "(uji internal gagal)"
echo "== Uji /v1/auth/login dari DALAM container (harus 401/400) =="
docker exec ${WEB_CONTAINER} python3 -c "import urllib.request,json;\nreq=urllib.request.Request('http://127.0.0.1:5000/v1/auth/login',data=b'{}',headers={'Content-Type':'application/json'},method='POST')\nimport sys\ntry:\n  r=urllib.request.urlopen(req,timeout=8);print('login ->',r.status)\nexcept urllib.error.HTTPError as e:\n  print('login ->',e.code)\nexcept Exception as e:\n  print('login ERROR:',e)" 2>/dev/null || echo "(uji login internal gagal)"
echo "== Uji HTTPS dari HOST server =="
curl -s -o /dev/null -w "host->https tarombo -> %{http_code}\n" -m 10 https://tarombo.ptsbi.org/ 2>/dev/null || echo "(host curl gagal)"
echo "== traefik log 8 baris =="
docker logs traefik --tail 8 2>/dev/null || true
echo "== git rev server =="
git -C "${REMOTE_DIR}" rev-parse --short HEAD 2>/dev/null || echo "(bukan git repo)"
REMOTE
}

# --- DEPLOY (rebuild web saja, DB & .env aman) ---
deploy_remote() {
  echo "==> Sinkron kode ke ${SSH_HOST}:${REMOTE_DIR} (tanpa --delete; .env, data/, backups/, config/ aman) ..."
  # Tanpa --delete: hanya menambah/memperbarui file, tidak menghapus file server (backup, config, .env).
  rsync -az \
    --exclude='.env' \
    --exclude='data/' \
    --exclude='backups/' \
    --exclude='config/' \
    --exclude='*.db' \
    --exclude='__pycache__/' \
    --exclude='.git/' \
    --exclude='.cursor/' \
    -e "ssh ${SSH_OPTS[*]}" \
    "${APP_SRC}/" "${SSH_HOST}:${REMOTE_DIR}/"

  ssh "${SSH_OPTS[@]}" "${SSH_HOST}" bash -s <<REMOTE
set -euo pipefail
cd "${REMOTE_DIR}"

# Tentukan compose file yang dipakai container yang sedang berjalan.
CFG="\$(docker inspect ${WEB_CONTAINER} --format '{{index .Config.Labels "com.docker.compose.project.config_files"}}' 2>/dev/null || true)"
COMPOSE_FILE=""
if [ -n "\$CFG" ] && [ -f "\$CFG" ]; then
  COMPOSE_FILE="\$CFG"
elif [ -f "${REMOTE_DIR}/docker-compose.server.yml" ]; then
  COMPOSE_FILE="${REMOTE_DIR}/docker-compose.server.yml"
elif [ -f "${REMOTE_DIR}/docker-compose.yml" ]; then
  COMPOSE_FILE="${REMOTE_DIR}/docker-compose.yml"
fi
echo "COMPOSE_FILE=\${COMPOSE_FILE:-<none>}"

if [ -n "\$COMPOSE_FILE" ]; then
  # Rebuild & recreate HANYA service web. DB container + volume tidak disentuh.
  docker compose -f "\$COMPOSE_FILE" build web
  docker compose -f "\$COMPOSE_FILE" up -d --no-deps web
else
  echo "Tidak menemukan compose file — rebuild manual image lalu restart container."
  docker build -t tarombo-app-web "${REMOTE_DIR}"
  docker restart ${WEB_CONTAINER} || true
fi

echo "== Menunggu web siap =="
sleep 6
docker logs ${WEB_CONTAINER} --tail 20 2>/dev/null || true

echo "== Status container =="
for c in ${WEB_CONTAINER} tarombo-postgres traefik; do
  if docker ps --format '{{.Names}}' | grep -qx "\$c"; then echo "UP=\$c"; else echo "DOWN=\$c"; fi
done
REMOTE

  echo "==> Verifikasi endpoint /v1 (POST tanpa key harus 401, bukan 404) ..."
  for p in auth/login members/profile settings/options settings/pdf-items; do
    code="$(curl -s -o /dev/null -w '%{http_code}' -m 15 -X POST -H 'Content-Type: application/json' -d '{}' "https://tarombo.ptsbi.org/v1/${p}" || echo 000)"
    echo "  /v1/${p} -> HTTP ${code}"
  done
}

inspect_remote
if [[ "${MODE}" == "deploy" ]]; then
  echo "==> Menjalankan deploy aman (rebuild web saja) ..."
  deploy_remote
  echo "==> Selesai deploy Tarombo."
else
  echo "==> Mode inspeksi selesai (tidak ada perubahan di server)."
fi
