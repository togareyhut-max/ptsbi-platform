#!/usr/bin/env bash
# remove-aagit.sh — Hapus user aagit dan /home/aagit dari server
#
# WAJIB sebelum jalankan:
#   - ssh togaa@server berhasil
#   - lpmpjk.org & ptsbi.org jalan dari /home/togaa
#   - Tidak ada container yang mount /home/aagit
#
#   chmod +x remove-aagit.sh
#   ./remove-aagit.sh

set -euo pipefail

OLD_USER="${OLD_USER:-aagit}"
OLD_HOME="/home/${OLD_USER}"
NEW_USER="${NEW_USER:-togaa}"
NEW_HOME="/home/${NEW_USER}"
ARCHIVE_ROOT="/root/archive-removed-users"

log() { echo "[$(date +%H:%M:%S)] $*"; }
die() { log "ERROR: $*"; exit 1; }

[[ "${EUID:-0}" -eq 0 ]] || die "Jalankan sebagai root"

confirm() {
  echo ""
  echo "PERINGATAN: User ${OLD_USER} dan ${OLD_HOME} akan dihapus permanen."
  echo "Pastikan semua stack jalan dari ${NEW_HOME}."
  read -r -p "Ketik YES untuk lanjut: " ans
  [[ "${ans}" == "YES" ]] || die "Dibatalkan."
}

preflight() {
  log "=== Preflight ==="
  id "${NEW_USER}" &>/dev/null || die "User ${NEW_USER} belum ada"
  [[ -d "${NEW_HOME}" ]] || die "${NEW_HOME} tidak ada — migrasi dulu"

  if grep -r "/home/${OLD_USER}" "${NEW_HOME}" \
    --include='*.yml' --include='*.yaml' --include='*.env' 2>/dev/null | grep -q .; then
    log "Masih ada referensi /home/${OLD_USER} di ${NEW_HOME}:"
    grep -r "/home/${OLD_USER}" "${NEW_HOME}" \
      --include='*.yml' --include='*.yaml' --include='*.env' 2>/dev/null || true
    die "Perbaiki path dulu sebelum hapus ${OLD_USER}"
  fi

  if docker ps -a --format '{{.Names}} {{.Mounts}}' 2>/dev/null | grep -q "/home/${OLD_USER}"; then
    log "Container masih mount ${OLD_HOME}:"
    docker ps -a --format '{{.Names}} {{.Mounts}}' | grep "${OLD_HOME}" || true
    die "Stop container yang masih pakai ${OLD_HOME}"
  fi

  if id "${OLD_USER}" &>/dev/null; then
    if pgrep -u "${OLD_USER}" &>/dev/null; then
      log "Proses masih berjalan sebagai ${OLD_USER}:"
      pgrep -u "${OLD_USER}" -a || true
      die "Hentikan proses user ${OLD_USER} dulu"
    fi
  else
    log "User ${OLD_USER} sudah tidak ada."
    [[ -d "${OLD_HOME}" ]] || { log "Selesai (tidak ada home)."; exit 0; }
  fi
}

stop_old_stacks() {
  [[ -d "${OLD_HOME}/traefik" ]] || return 0
  log "Stop compose lama di ${OLD_HOME} (jika masih ada)..."
  for d in traefik mysql wordpress tarombo-app; do
    [[ -f "${OLD_HOME}/${d}/docker-compose.yml" ]] || continue
    (cd "${OLD_HOME}/${d}" && docker compose down 2>/dev/null) || true
  done
}

archive_home() {
  mkdir -p "${ARCHIVE_ROOT}"
  if [[ -d "${OLD_HOME}" ]]; then
    local dest="${ARCHIVE_ROOT}/${OLD_USER}-home-$(date +%Y%m%d).tar.gz"
    log "Arsip ${OLD_HOME} → ${dest}"
    tar -czf "${dest}" -C /home "${OLD_USER}" 2>/dev/null || tar -czf "${dest}" "${OLD_HOME}"
    log "Arsip selesai: $(ls -lh "${dest}")"
  fi
  # Folder disabled dari migrasi sebelumnya
  for d in "${OLD_HOME}.disabled."*; do
    [[ -d "${d}" ]] || continue
    local dest="${ARCHIVE_ROOT}/$(basename "${d}")-$(date +%Y%m%d).tar.gz"
    log "Arsip ${d} → ${dest}"
    tar -czf "${dest}" "${d}"
    rm -rf "${d}"
  done
}

remove_user() {
  if id "${OLD_USER}" &>/dev/null; then
    log "Hapus user ${OLD_USER} (-r)..."
    userdel -r "${OLD_USER}" 2>/dev/null || {
      log "userdel -r gagal — coba lock lalu hapus manual"
      usermod -L "${OLD_USER}" 2>/dev/null || true
      usermod -s /usr/sbin/nologin "${OLD_USER}" 2>/dev/null || true
      die "userdel gagal — cek proses/file milik ${OLD_USER}"
    }
    log "User ${OLD_USER} dihapus."
  fi
  if [[ -d "${OLD_HOME}" ]]; then
    log "Sisa folder ${OLD_HOME} — menghapus..."
    rm -rf "${OLD_HOME}"
  fi
}

cleanup_misc() {
  log "Bersihkan crontab (jika ada)..."
  crontab -u "${OLD_USER}" -r 2>/dev/null || true
  log "Cek referensi tersisa di /etc..."
  grep -r "${OLD_USER}" /etc/cron* /var/spool/cron 2>/dev/null || log "  (tidak ada di cron)"
  log "Selesai. Kelola server hanya dengan ${NEW_USER}."
}

main() {
  confirm
  preflight
  stop_old_stacks
  archive_home
  remove_user
  cleanup_misc
  log "=== aagit tidak ada lagi di server ==="
}

main "$@"
