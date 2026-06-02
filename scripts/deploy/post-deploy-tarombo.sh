#!/usr/bin/env bash
# Jalankan di server setelah rsync kode Tarombo.
#   cd /home/togaa/tarombo-app && bash scripts/post-deploy-tarombo.sh
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "${APP_DIR}"

if [[ ! -f docker-compose.server.yml ]]; then
  echo "ERROR: docker-compose.server.yml tidak ditemukan di ${APP_DIR}"
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "ERROR: .env belum ada. Salin dari .env.example lalu isi POSTGRES_PASSWORD dan SECRET_KEY."
  exit 1
fi

if [[ -f scripts/backup-tarombo.sh ]]; then
  echo "==> Backup sebelum deploy..."
  bash scripts/backup-tarombo.sh
fi

echo "==> Build & restart container..."
docker compose -f docker-compose.server.yml up -d --build

echo "==> Healthcheck (auto heal jika perlu)..."
set +e
docker compose -f docker-compose.server.yml ps >/dev/null 2>&1
docker restart tarombo-web >/dev/null 2>&1 || true
docker restart tarombo-postgres >/dev/null 2>&1 || true
set -e

echo "==> Tunggu Postgres sehat..."
for i in {1..30}; do
  if docker inspect -f '{{json .State.Health.Status}}' tarombo-postgres 2>/dev/null | grep -q '"healthy"'; then
    break
  fi
  sleep 2
done

echo "==> Ping API (coba self-heal jika belum OK)..."
for i in {1..20}; do
  if curl -fsS "https://tarombo.ptsbi.org/v1/ping" >/dev/null 2>&1; then
    echo "OK: /v1/ping"
    break
  fi
  echo "Ping gagal, restart web... ($i/20)"
  docker restart tarombo-web >/dev/null 2>&1 || true
  sleep 2
done

echo "==> Cek DB health (/v1/health/db)..."
if curl -fsS "https://tarombo.ptsbi.org/v1/health/db" >/dev/null 2>&1; then
  echo "OK: /v1/health/db"
else
  echo "WARNING: DB health gagal. Coba restart postgres..."
  docker restart tarombo-postgres >/dev/null 2>&1 || true
  sleep 2
  curl -fsS "https://tarombo.ptsbi.org/v1/health/db" >/dev/null 2>&1 || echo "ERROR: DB masih down"
fi

echo "==> Status container:"
docker compose -f docker-compose.server.yml ps

echo "==> Log web (10 baris terakhir):"
docker logs tarombo-web --tail 10 2>&1 || true

echo ""
echo "Deploy Tarombo selesai. Tes: https://tarombo.ptsbi.org"
