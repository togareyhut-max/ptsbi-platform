#!/usr/bin/env bash
# deploy-tarombo-traefik.sh — Naikkan tarombo-app baru + Traefik (tarombo.ptsbi.org)
# Jalankan di server sebagai root, dari folder /home/togaa/tarombo-app
#
#   chmod +x deploy-tarombo-traefik.sh
#   ./deploy-tarombo-traefik.sh

set -euo pipefail

APP_DIR="${APP_DIR:-/home/togaa/tarombo-app}"
COMPOSE_FILE="docker-compose.server.yml"
DOMAIN="tarombo.ptsbi.org"

log() { echo "[$(date +%H:%M:%S)] $*"; }
die() { log "ERROR: $*"; exit 1; }

[[ "${EUID:-0}" -eq 0 ]] || die "Jalankan sebagai root (atau sudo)"

[[ -d "${APP_DIR}" ]] || die "Folder tidak ada: ${APP_DIR}"
[[ -f "${APP_DIR}/${COMPOSE_FILE}" ]] || die "Tidak ada ${APP_DIR}/${COMPOSE_FILE}"
[[ -f "${APP_DIR}/.env" ]] || die "Buat ${APP_DIR}/.env dari .env.example dulu"

cd "${APP_DIR}"

log "Cek network traefik..."
docker network inspect traefik &>/dev/null || die "Network 'traefik' tidak ada. Start traefik dulu: cd /home/togaa/traefik && docker compose up -d"

log "Cek certresolver di traefik (harus ada 'letsencrypt')..."
if ! docker inspect traefik --format '{{range $k,$v := .Config.Labels}}{{$k}}={{$v}}{{"\n"}}{{end}}' 2>/dev/null | head -1 >/dev/null; then
  log "Traefik container OK"
fi
grep -r "letsencrypt\|certificatesResolvers" /home/togaa/traefik/ 2>/dev/null | head -5 | tee /tmp/traefik-cert-hint.txt || true
log "Jika certresolver bukan 'letsencrypt', edit label di ${COMPOSE_FILE}"

log "Stop stack tarombo lama (jika ada)..."
docker compose -f docker-compose.yml down 2>/dev/null || true
docker compose -f docker-compose.server.yml down 2>/dev/null || true
docker rm -f tarombo-web tarombo-postgres 2>/dev/null || true

log "Build & start (PostgreSQL + web + Traefik labels)..."
docker compose -f "${COMPOSE_FILE}" up -d --build

log "Menunggu health postgres..."
sleep 8
docker compose -f "${COMPOSE_FILE}" ps
docker logs tarombo-web --tail 25

log "Tes HTTPS..."
code="$(curl -k -s -o /dev/null -w '%{http_code}' --max-time 20 "https://${DOMAIN}/" || echo 000)"
log "https://${DOMAIN}/ → HTTP ${code}"

if [[ "${code}" =~ ^(200|302|303|307|308)$ ]]; then
  log "Deploy OK."
else
  log "Deploy selesai tapi HTTP ${code} — cek: docker logs traefik --tail 30"
fi

cat <<EOF

Akun seed (password 12345678, ganti setelah login):
  admin@ptsbi.org / pengurus@ptsbi.org / anggota@ptsbi.org / developer@ptsbi.org

Database: PostgreSQL baru (volume pgdata). Data silsilah lama MySQL tidak otomatis pindah.

EOF
