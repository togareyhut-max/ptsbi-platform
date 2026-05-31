#!/usr/bin/env bash
# restore-tarombo.sh — Pulihkan database + data Tarombo dari backup (ROLLBACK).
#
# Jalankan DARI folder aplikasi:
#   cd /home/togaa/tarombo-app
#   bash scripts/restore-tarombo.sh backups/tarombo-db-YYYYmmdd-HHMMSS.sql.gz \
#        [backups/tarombo-data-YYYYmmdd-HHMMSS.tar.gz]
#
# CATATAN: ini MENIMPA database yang sedang aktif. Pastikan Anda sudah backup
# kondisi terbaru dulu (scripts/backup-tarombo.sh) sebelum restore.
set -euo pipefail

APP_DIR="${APP_DIR:-$(pwd)}"
PG_CONTAINER="${PG_CONTAINER:-tarombo-postgres}"
WEB_CONTAINER="${WEB_CONTAINER:-tarombo-web}"
DB_DUMP="${1:-}"
DATA_TAR="${2:-}"

cd "${APP_DIR}"

if [[ -z "${DB_DUMP}" || ! -f "${DB_DUMP}" ]]; then
  echo "Pakai: bash scripts/restore-tarombo.sh <db-dump.sql.gz> [data.tar.gz]"
  echo "Daftar backup tersedia:"
  ls -1 backups/tarombo-db-* 2>/dev/null || echo "  (tidak ada backup)"
  exit 1
fi

PG_USER="$(grep -E '^POSTGRES_USER=' .env | head -1 | cut -d= -f2- || true)"
PG_DB="$(grep -E '^POSTGRES_DB=' .env | head -1 | cut -d= -f2- || true)"
PG_USER="${PG_USER:-tarombo}"
PG_DB="${PG_DB:-tarombo_ptsbi}"

echo "AKAN MENIMPA database '${PG_DB}' dengan: ${DB_DUMP}"
read -r -p "Ketik 'YA' untuk lanjut: " ans
[[ "${ans}" == "YA" ]] || { echo "Dibatalkan."; exit 1; }

echo "==> Hentikan web container agar tidak menulis saat restore..."
docker stop "${WEB_CONTAINER}" 2>/dev/null || true

echo "==> Drop & buat ulang database ${PG_DB}..."
docker exec -i "${PG_CONTAINER}" psql -U "${PG_USER}" -d postgres -v ON_ERROR_STOP=1 <<SQL
SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '${PG_DB}' AND pid <> pg_backend_pid();
DROP DATABASE IF EXISTS ${PG_DB};
CREATE DATABASE ${PG_DB} OWNER ${PG_USER};
SQL

echo "==> Impor dump..."
gunzip -c "${DB_DUMP}" | docker exec -i "${PG_CONTAINER}" psql -U "${PG_USER}" -d "${PG_DB}" -v ON_ERROR_STOP=1

if [[ -n "${DATA_TAR}" && -f "${DATA_TAR}" ]]; then
  echo "==> Pulihkan folder data dari ${DATA_TAR}..."
  tar xzf "${DATA_TAR}" -C "${APP_DIR}"
fi

echo "==> Nyalakan kembali web container..."
docker start "${WEB_CONTAINER}" 2>/dev/null || \
  docker compose -f docker-compose.server.yml up -d web

echo ""
echo "Restore selesai. Cek: https://tarombo.ptsbi.org dan 'docker logs ${WEB_CONTAINER}'"
