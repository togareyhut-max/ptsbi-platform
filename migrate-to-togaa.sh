#!/usr/bin/env bash
# migrate-to-togaa.sh — Migrasi /home/aagit → /home/togaa (vm21197)
#
# Jalankan sebagai root pada malam maintenance:
#   chmod +x migrate-to-togaa.sh
#   ./migrate-to-togaa.sh migrate
#
# Setelah 24–48 jam semua domain OK:
#   ./migrate-to-togaa.sh finalize
#
# Rollback (jika gagal sebelum finalize):
#   ./migrate-to-togaa.sh rollback

set -euo pipefail

SOURCE_USER="${SOURCE_USER:-aagit}"
TARGET_USER="${TARGET_USER:-togaa}"
SOURCE_HOME="/home/${SOURCE_USER}"
TARGET_HOME="/home/${TARGET_USER}"
LOG_DIR="/root/migrate-togaa-$(date +%Y%m%d-%H%M%S)"
LOG_FILE=""  # set in main

# Stack paths (relative to home)
STACKS=(
  "traefik"
  "mysql"
  "wordpress"
  "tarombo-app"
)

DOMAINS=(
  "https://lpmpjk.org"
  "https://ptsbi.org"
  "https://tarombo.ptsbi.org"
)

log() {
  local msg="[$(date +%H:%M:%S)] $*"
  echo "$msg" | tee -a "${LOG_FILE}"
}

die() {
  log "ERROR: $*"
  exit 1
}

require_root() {
  [[ "${EUID:-$(id -u)}" -eq 0 ]] || die "Jalankan sebagai root: sudo $0 $*"
}

init_log() {
  mkdir -p "${LOG_DIR}"
  LOG_FILE="${LOG_DIR}/migrate.log"
  log "Log: ${LOG_FILE}"
}

backup_exists_check() {
  if [[ ! -d /root/backup-pre-migrate ]] || ! ls /root/backup-pre-migrate/home-aagit-*.tar.gz &>/dev/null; then
    log "PERINGATAN: Backup /root/backup-pre-migrate/home-aagit-*.tar.gz tidak ditemukan."
    log "Disarankan: buat dulu sebelum lanjut (Ctrl+C untuk batal)."
    sleep 5
  else
    log "Backup OK: $(ls -lh /root/backup-pre-migrate/home-aagit-*.tar.gz | tail -1)"
  fi
}

# Tarombo dilayani lewat Traefik: WAJIB pakai docker-compose.server.yml,
# bukan docker-compose.yml (dev, localhost:5000, tanpa label Traefik).
compose_file_flag() {
  local dir="$1"
  if [[ "$(basename "${dir}")" == "tarombo-app" ]] && [[ -f "${dir}/docker-compose.server.yml" ]]; then
    echo "-f docker-compose.server.yml"
  fi
}

compose_down() {
  local home="$1"
  local stack="$2"
  local dir="${home}/${stack}"
  local file_flag
  file_flag="$(compose_file_flag "${dir}")"
  if [[ -f "${dir}/docker-compose.yml" ]] || [[ -f "${dir}/compose.yml" ]] || [[ -n "${file_flag}" ]]; then
    log "Stop stack: ${dir} ${file_flag}"
    (cd "${dir}" && docker compose ${file_flag} down --remove-orphans 2>&1 | tee -a "${LOG_FILE}") || true
  fi
}

compose_up() {
  local home="$1"
  local stack="$2"
  local extra="${3:-}"
  local dir="${home}/${stack}"
  local file_flag
  file_flag="$(compose_file_flag "${dir}")"
  if [[ ! -f "${dir}/docker-compose.yml" ]] && [[ -z "${file_flag}" ]]; then
    log "Lewati (tidak ada compose): ${dir}"
    return 0
  fi
  log "Start stack: ${dir} ${file_flag} ${extra}"
  (cd "${dir}" && docker compose ${file_flag} up -d ${extra} 2>&1 | tee -a "${LOG_FILE}")
}

stop_all_stacks() {
  local home="$1"
  log "=== Menghentikan semua container (urutan aman) ==="
  compose_down "${home}" "traefik"
  compose_down "${home}" "wordpress"
  compose_down "${home}" "tarombo-app"
  compose_down "${home}" "mysql"
  if [[ -n "$(docker ps -q 2>/dev/null || true)" ]]; then
    log "Container masih berjalan:"
    docker ps | tee -a "${LOG_FILE}"
    die "Masih ada container aktif. Stop manual lalu ulangi."
  fi
  log "Semua container berhenti."
}

start_all_stacks() {
  local home="$1"
  log "=== Menjalankan stack dari ${home} ==="
  compose_up "${home}" "mysql"
  log "Menunggu MySQL (15 detik)..."
  sleep 15
  compose_up "${home}" "wordpress"
  compose_up "${home}" "tarombo-app" "--build"
  compose_up "${home}" "traefik"
  sleep 5
  docker ps | tee -a "${LOG_FILE}"
}

create_togaa_user() {
  if id "${TARGET_USER}" &>/dev/null; then
    log "User ${TARGET_USER} sudah ada."
  else
    log "Membuat user ${TARGET_USER}..."
    adduser "${TARGET_USER}" --disabled-password --gecos "" || die "gagal adduser"
  fi
  usermod -aG sudo,docker "${TARGET_USER}" 2>/dev/null || true

  mkdir -p "${TARGET_HOME}/.ssh"
  chmod 700 "${TARGET_HOME}/.ssh"
  if [[ ! -s "${TARGET_HOME}/.ssh/authorized_keys" ]]; then
    if [[ -s "${SOURCE_HOME}/.ssh/authorized_keys" ]]; then
      cp "${SOURCE_HOME}/.ssh/authorized_keys" "${TARGET_HOME}/.ssh/authorized_keys"
      log "SSH key disalin dari ${SOURCE_USER}."
    else
      log "PERINGATAN: Tidak ada authorized_keys di ${SOURCE_HOME}. Pasang key manual ke ${TARGET_HOME}/.ssh/"
    fi
  fi
  chown -R "${TARGET_USER}:${TARGET_USER}" "${TARGET_HOME}/.ssh"
  chmod 600 "${TARGET_HOME}/.ssh/authorized_keys" 2>/dev/null || true
}

rsync_home() {
  log "=== rsync ${SOURCE_HOME} → ${TARGET_HOME} ==="
  [[ -d "${SOURCE_HOME}" ]] || die "${SOURCE_HOME} tidak ada"

  if [[ -d "${TARGET_HOME}" ]] && [[ -n "$(ls -A "${TARGET_HOME}" 2>/dev/null || true)" ]]; then
    log "Target sudah ada isi — backup ke ${TARGET_HOME}.pre-migrate.bak"
    mv "${TARGET_HOME}" "${TARGET_HOME}.pre-migrate.bak.$(date +%s)" 2>/dev/null || true
  fi

  mkdir -p "${TARGET_HOME}"
  rsync -aHAX --info=progress2 "${SOURCE_HOME}/" "${TARGET_HOME}/" 2>&1 | tee -a "${LOG_FILE}"
  log "rsync selesai."
}

replace_paths() {
  log "=== Mengganti path /home/aagit → /home/togaa di config ==="
  grep -rl "/home/${SOURCE_USER}" "${TARGET_HOME}" \
    --include='*.yml' --include='*.yaml' --include='*.env' --include='*.sh' \
    2>/dev/null | while read -r f; do
      sed -i "s|/home/${SOURCE_USER}|/home/${TARGET_USER}|g" "$f"
      log "  updated: $f"
    done || true
}

fix_permissions() {
  log "=== Permission & ownership ==="
  chown "${TARGET_USER}:${TARGET_USER}" "${TARGET_HOME}"
  chmod 750 "${TARGET_HOME}"

  if [[ -d "${TARGET_HOME}/traefik" ]]; then
    chown "${TARGET_USER}:${TARGET_USER}" "${TARGET_HOME}/traefik"
    chmod 775 "${TARGET_HOME}/traefik"
  fi

  if [[ -d "${TARGET_HOME}/traefik/data" ]]; then
    chown -R root:root "${TARGET_HOME}/traefik/data"
    find "${TARGET_HOME}/traefik/data" -type d -exec chmod 755 {} \;
  fi

  if [[ -f "${TARGET_HOME}/traefik/data/letsencrypt/acme.json" ]]; then
    chown root:root "${TARGET_HOME}/traefik/data/letsencrypt/acme.json"
    chmod 600 "${TARGET_HOME}/traefik/data/letsencrypt/acme.json"
    log "acme.json: $(stat -c '%a %U:%G %s bytes' "${TARGET_HOME}/traefik/data/letsencrypt/acme.json")"
  fi

  for d in mysql wordpress tarombo-app docker; do
    [[ -d "${TARGET_HOME}/${d}" ]] || continue
    chown -R root:root "${TARGET_HOME}/${d}" 2>/dev/null || true
    find "${TARGET_HOME}/${d}" -type d -exec chmod 755 {} \; 2>/dev/null || true
  done

  if [[ -d "${TARGET_HOME}/docker/volumes/mysqldata" ]]; then
    chown -R 999:999 "${TARGET_HOME}/docker/volumes/mysqldata" 2>/dev/null || \
      log "PERINGATAN: chown mysqldata UID 999 — cek manual jika MySQL gagal start"
  fi
}

verify_domains() {
  log "=== Cek HTTPS (butuh DNS mengarah ke server ini) ==="
  local ok=0 fail=0
  for url in "${DOMAINS[@]}"; do
    local code
    code="$(curl -k -s -o /dev/null -w '%{http_code}' --max-time 15 "${url}" || echo "000")"
    if [[ "${code}" =~ ^(200|301|302|303|307|308)$ ]]; then
      log "  OK  ${url} → HTTP ${code}"
      ok=$((ok + 1))
    else
      log "  FAIL ${url} → HTTP ${code}"
      fail=$((fail + 1))
    fi
  done
  log "Hasil: ${ok} OK, ${fail} FAIL"
  [[ "${fail}" -eq 0 ]] || log "Beberapa domain gagal — cek docker logs traefik / container terkait."
}

cmd_migrate() {
  require_root
  init_log
  backup_exists_check

  log "========== MIGRASI ${SOURCE_HOME} → ${TARGET_HOME} =========="
  log "Hostname: $(hostname -f 2>/dev/null || hostname)"

  create_togaa_user
  stop_all_stacks "${SOURCE_HOME}"
  rsync_home
  replace_paths
  fix_permissions
  start_all_stacks "${TARGET_HOME}"
  verify_domains

  cat <<EOF | tee -a "${LOG_FILE}"

================================================================================
  MIGRASI SELESAI
================================================================================
  Log     : ${LOG_FILE}
  Data    : ${TARGET_HOME}
  SSH     : ssh ${TARGET_USER}@$(hostname -f 2>/dev/null || echo SERVER)
  WinSCP  : user ${TARGET_USER}, path ${TARGET_HOME}

  JANGAN jalankan finalize hari ini kecuali semua domain sudah OK 24–48 jam.

  Langkah Anda malam ini:
    1. Tes browser: lpmpjk.org, ptsbi.org, tarombo.ptsbi.org
    2. Tes SSH: ssh ${TARGET_USER}@IP  (terminal baru, jangan tutup root dulu)
    3. Besok/lusa jika OK: ./migrate-to-togaa.sh finalize

  Rollback jika gagal:
    ./migrate-to-togaa.sh rollback

================================================================================
EOF
}

cmd_finalize() {
  require_root
  init_log
  log "========== FINALIZE: kunci user lama & SSH =========="

  verify_domains

  for u in adminuser "${SOURCE_USER}"; do
    if id "${u}" &>/dev/null; then
      log "Lock user: ${u}"
      usermod -L "${u}" 2>/dev/null || true
      usermod -s /usr/sbin/nologin "${u}" 2>/dev/null || true
    fi
  done

  if [[ -d "${SOURCE_HOME}" ]] && [[ ! -L "${SOURCE_HOME}.disabled" ]]; then
    log "Rename ${SOURCE_HOME} → ${SOURCE_HOME}.disabled.$(date +%Y%m%d)"
    stop_all_stacks "${SOURCE_HOME}" 2>/dev/null || true
    mv "${SOURCE_HOME}" "${SOURCE_HOME}.disabled.$(date +%Y%m%d)" || true
  fi

  # SSH hardening (backup config dulu)
  if [[ -f /etc/ssh/sshd_config ]]; then
    cp -a /etc/ssh/sshd_config "/etc/ssh/sshd_config.bak.$(date +%Y%m%d)"
    sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
    sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
    if grep -q '^AllowUsers' /etc/ssh/sshd_config; then
      sed -i "s/^AllowUsers.*/AllowUsers ${TARGET_USER}/" /etc/ssh/sshd_config
    else
      echo "AllowUsers ${TARGET_USER}" >> /etc/ssh/sshd_config
    fi
    sshd -t && systemctl reload ssh
    log "SSH: PermitRootLogin no, PasswordAuthentication no, AllowUsers ${TARGET_USER}"
    log "PENTING: Pastikan login ssh ${TARGET_USER}@server berhasil SEBELUM tutup sesi root!"
  fi

  log "Finalize selesai. Kelola server hanya lewat ${TARGET_USER}."
}

cmd_rollback() {
  require_root
  init_log
  log "========== ROLLBACK ke ${SOURCE_HOME} =========="

  stop_all_stacks "${TARGET_HOME}" 2>/dev/null || true

  if [[ -d "${SOURCE_HOME}.disabled."* ]] 2>/dev/null; then
    latest="$(ls -d ${SOURCE_HOME}.disabled.* 2>/dev/null | sort | tail -1)"
    if [[ ! -d "${SOURCE_HOME}" ]] && [[ -n "${latest}" ]]; then
      mv "${latest}" "${SOURCE_HOME}"
      log "Dipulihkan: ${latest} → ${SOURCE_HOME}"
    fi
  fi

  [[ -d "${SOURCE_HOME}" ]] || die "Tidak ada ${SOURCE_HOME} untuk rollback"

  start_all_stacks "${SOURCE_HOME}"
  verify_domains
  log "Rollback selesai — stack dari ${SOURCE_HOME}."
}

usage() {
  cat <<'EOF'
Penggunaan:
  ./migrate-to-togaa.sh migrate    # Malam maintenance (utama)
  ./migrate-to-togaa.sh finalize   # 24–48 jam setelah migrate OK
  ./migrate-to-togaa.sh rollback   # Jika migrate gagal

Environment (opsional):
  SOURCE_USER=aagit  TARGET_USER=togaa

EOF
}

main() {
  local cmd="${1:-}"
  case "${cmd}" in
    migrate)  cmd_migrate ;;
    finalize) cmd_finalize ;;
    rollback) cmd_rollback ;;
    *) usage; exit 1 ;;
  esac
}

main "$@"
