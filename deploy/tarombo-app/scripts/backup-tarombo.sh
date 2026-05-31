#!/usr/bin/env bash
# backup-tarombo.sh — Backup database PostgreSQL + data (logo/upload) Tarombo.
#
# Jalankan DARI folder aplikasi (yang berisi .env + docker-compose.server.yml):
#   cd /home/togaa/tarombo-app
#   bash scripts/backup-tarombo.sh
#
# Hasil disimpan ke ./backups/ :
#   tarombo-db-YYYYmmdd-HHMMSS.sql.gz   (dump PostgreSQL)
#   tarombo-data-YYYYmmdd-HHMMSS.tar.gz (folder data: logo & upload)
#   env-YYYYmmdd-HHMMSS.bak             (salinan .env, berisi password — chmod 600)
#
# Backup ini = TITIK ROLLBACK. Simpan sebelum setiap deploy/update.
set -euo pipefail

APP_DIR="${APP_DIR:-$(pwd)}"
PG_CONTAINER="${PG_CONTAINER:-tarombo-postgres}"
BACKUP_DIR="${BACKUP_DIR:-${APP_DIR}/backups}"
KEEP="${KEEP:-14}"            # simpan 14 backup terbaru per jenis
TS="$(date +%Y%m%d-%H%M%S)"

cd "${APP_DIR}"

if [[ ! -f .env ]]; then
  echo "ERROR: .env tidak ada di ${APP_DIR}. Jalankan dari folder aplikasi."
  exit 1
fi

# Ambil kredensial DB dari .env
PG_USER="$(grep -E '^POSTGRES_USER=' .env | head -1 | cut -d= -f2- || true)"
PG_DB="$(grep -E '^POSTGRES_DB=' .env | head -1 | cut -d= -f2- || true)"
PG_USER="${PG_USER:-tarombo}"
PG_DB="${PG_DB:-tarombo_ptsbi}"

if ! docker ps --format '{{.Names}}' | grep -qx "${PG_CONTAINER}"; then
  echo "ERROR: container ${PG_CONTAINER} tidak berjalan. Start dulu stack-nya."
  exit 1
fi

mkdir -p "${BACKUP_DIR}"

echo "==> Dump database ${PG_DB} (user ${PG_USER})..."
docker exec "${PG_CONTAINER}" pg_dump -U "${PG_USER}" -d "${PG_DB}" \
  | gzip > "${BACKUP_DIR}/tarombo-db-${TS}.sql.gz"
echo "    -> ${BACKUP_DIR}/tarombo-db-${TS}.sql.gz"

echo "==> Arsipkan folder data (logo & upload)..."
if [[ -d data ]]; then
  tar czf "${BACKUP_DIR}/tarombo-data-${TS}.tar.gz" data
  echo "    -> ${BACKUP_DIR}/tarombo-data-${TS}.tar.gz"
else
  echo "    (folder data/ tidak ada — dilewati)"
fi

echo "==> Salin .env (berisi password — akses 600)..."
cp .env "${BACKUP_DIR}/env-${TS}.bak"
chmod 600 "${BACKUP_DIR}/env-${TS}.bak"

# Verifikasi dump tidak kosong
SIZE="$(stat -c '%s' "${BACKUP_DIR}/tarombo-db-${TS}.sql.gz" 2>/dev/null || echo 0)"
if [[ "${SIZE}" -lt 100 ]]; then
  echo "PERINGATAN: dump database mencurigakan kecil (${SIZE} bytes). Cek manual!"
fi

echo "==> Bersihkan backup lama (simpan ${KEEP} terbaru per jenis)..."
for prefix in tarombo-db tarombo-data env; do
  ls -1t "${BACKUP_DIR}/${prefix}-"* 2>/dev/null | tail -n +$((KEEP + 1)) | while read -r old; do
    rm -f "${old}" && echo "    hapus lama: ${old}"
  done
done

echo ""
echo "Backup selesai (${TS}). Isi folder backups:"
ls -lh "${BACKUP_DIR}" | tail -n +2
