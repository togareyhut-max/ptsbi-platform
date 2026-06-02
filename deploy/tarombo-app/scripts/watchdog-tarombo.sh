#!/usr/bin/env bash
# Watchdog sederhana: pastikan Tarombo API & Postgres tetap hidup.
# Aman dijalankan via systemd timer (lihat docs).
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "${APP_DIR}"

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.server.yml}"
PING_URL="${PING_URL:-https://tarombo.ptsbi.org/v1/ping}"
DB_URL="${DB_URL:-https://tarombo.ptsbi.org/v1/health/db}"

log() { echo "[watchdog] $*"; }

log "ensure containers up..."
docker compose -f "${COMPOSE_FILE}" up -d >/dev/null 2>&1 || true

if docker inspect -f '{{json .State.Health.Status}}' tarombo-postgres 2>/dev/null | grep -q '"unhealthy"'; then
  log "postgres unhealthy -> restart"
  docker restart tarombo-postgres >/dev/null 2>&1 || true
fi

if ! curl -fsS "${DB_URL}" >/dev/null 2>&1; then
  log "db health failed -> restart postgres"
  docker restart tarombo-postgres >/dev/null 2>&1 || true
  sleep 2
fi

if ! curl -fsS "${PING_URL}" >/dev/null 2>&1; then
  log "ping failed -> restart web"
  docker restart tarombo-web >/dev/null 2>&1 || true
  sleep 2
fi

if ! curl -fsS "${PING_URL}" >/dev/null 2>&1; then
  log "ping still failed -> rebuild compose"
  docker compose -f "${COMPOSE_FILE}" up -d --build >/dev/null 2>&1 || true
  sleep 2
fi

if curl -fsS "${PING_URL}" >/dev/null 2>&1; then
  log "OK"
  exit 0
fi

log "ERROR: API still down after self-heal"
exit 2

