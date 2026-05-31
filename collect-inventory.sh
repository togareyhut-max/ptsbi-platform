#!/usr/bin/env bash
# collect-inventory.sh — Inventaris server sebelum migrasi aagit → togaa
# Jalankan di server Ubuntu sebagai root:
#   chmod +x collect-inventory.sh && sudo ./collect-inventory.sh
#
# Output: /tmp/server-inventory-YYYYMMDD-HHMMSS/
# Arsip:  /tmp/server-inventory-YYYYMMDD-HHMMSS.tgz

set -euo pipefail

SOURCE_USER="${SOURCE_USER:-aagit}"
SOURCE_HOME="/home/${SOURCE_USER}"
TARGET_USER="${TARGET_USER:-togaa}"
TARGET_HOME="/home/${TARGET_USER}"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT_DIR="/tmp/server-inventory-${STAMP}"
REPORT="${OUT_DIR}/INVENTORY_REPORT.txt"
MASK_SECRETS="${MASK_SECRETS:-1}"

mkdir -p "${OUT_DIR}"/{configs,tree,scripts,docker}

log() { echo "[$(date +%H:%M:%S)] $*" >&2; }
section() {
  local title="$1"
  {
    echo ""
    echo "================================================================================"
    echo "  ${title}"
    echo "================================================================================"
    echo ""
  } | tee -a "${REPORT}"
}

run_cmd() {
  local desc="$1"
  shift
  {
    echo "### ${desc}"
    echo "\$ $*"
    echo "---"
    "$@" 2>&1 || echo "[exit code: $?]"
    echo ""
  } >> "${REPORT}" 2>&1
}

mask_env_file() {
  local src="$1"
  local dest="$2"
  if [[ "${MASK_SECRETS}" != "1" ]]; then
    cp -a "${src}" "${dest}"
    return
  fi
  sed -E \
    -e 's/^(.*(PASSWORD|PASS|SECRET|TOKEN|KEY|API_KEY|AUTH|PRIVATE|CREDENTIAL)[^=]*)=.*/\1=***REDACTED***/Ii' \
    -e 's/^(MYSQL_ROOT_PASSWORD)=.*/\1=***REDACTED***/' \
    -e 's/^(WORDPRESS_DB_PASSWORD)=.*/\1=***REDACTED***/' \
    "${src}" > "${dest}" 2>/dev/null || cp -a "${src}" "${dest}"
}

copy_config_tree() {
  local base="$1"
  local rel_dest="$2"
  if [[ ! -d "${base}" ]]; then
    return
  fi
  local dest="${OUT_DIR}/configs/${rel_dest}"
  mkdir -p "${dest}"

  while IFS= read -r -d '' f; do
    local rel="${f#${base}/}"
    local target="${dest}/${rel}"
    mkdir -p "$(dirname "${target}")"
    case "${f}" in
      *.env|.env|*.env.*)
        mask_env_file "${f}" "${target}"
        ;;
      acme.json)
        # Jangan salin isi sertifikat — hanya metadata
        stat "${f}" > "${target}.stat.txt" 2>&1 || true
        echo "[acme.json omitted — metadata in .stat.txt]" > "${target}"
        ;;
      *)
        cp -a "${f}" "${target}" 2>/dev/null || true
        ;;
    esac
  done < <(find "${base}" -type f \( \
    -name 'docker-compose.yml' -o -name 'docker-compose.yaml' -o -name 'compose.yml' -o -name 'compose.yaml' \
    -o -name '.env' -o -name '.env.*' -o -name 'traefik.yml' -o -name 'traefik.yaml' -o -name 'traefik.toml' \
    -o -name '*.yml' -o -name '*.yaml' -o -name '*.toml' -o -name '*.sh' -o -name 'Caddyfile' \
    -o -name 'wp-config.php' -o -name '.htaccess' \
  \) ! -path '*/node_modules/*' ! -path '*/vendor/*' ! -path '*/.git/*' -print0 2>/dev/null)
}

log "Mulai inventaris → ${OUT_DIR}"

{
  echo "SERVER INVENTORY REPORT"
  echo "Generated : $(date -Iseconds)"
  echo "Hostname  : $(hostname -f 2>/dev/null || hostname)"
  echo "Source    : ${SOURCE_HOME} (user: ${SOURCE_USER})"
  echo "Target    : ${TARGET_HOME} (user: ${TARGET_USER})"
  echo "Mask secrets: ${MASK_SECRETS}"
} > "${REPORT}"

# --- System ---
section "1. SYSTEM"
run_cmd "OS release" cat /etc/os-release
run_cmd "Kernel" uname -a
run_cmd "Timezone" timedatectl status
run_cmd "Disk usage" df -hT
run_cmd "Memory" free -h
run_cmd "Public IP (if available)" bash -c 'curl -fsS --max-time 3 ifconfig.me 2>/dev/null || echo "N/A"'

# --- Users ---
section "2. USERS & SSH"
run_cmd "Passwd entries" bash -c "getent passwd ${SOURCE_USER} ${TARGET_USER} root 2>/dev/null || true"
run_cmd "Groups aagit" groups "${SOURCE_USER}" 2>/dev/null || true
run_cmd "Groups togaa" groups "${TARGET_USER}" 2>/dev/null || true
run_cmd "SSH directory aagit" ls -la "${SOURCE_HOME}/.ssh" 2>/dev/null || echo "No .ssh"
run_cmd "SSH directory togaa" ls -la "${TARGET_HOME}/.ssh" 2>/dev/null || echo "No .ssh / user belum ada"
if [[ -f /etc/ssh/sshd_config ]]; then
  run_cmd "SSHd security lines" grep -E '^(Port|PermitRootLogin|PasswordAuthentication|PubkeyAuthentication|AllowUsers|AllowGroups)' /etc/ssh/sshd_config || true
fi

# --- Home structure ---
section "3. HOME STRUCTURE (${SOURCE_HOME})"
if [[ ! -d "${SOURCE_HOME}" ]]; then
  echo "ERROR: ${SOURCE_HOME} tidak ditemukan!" | tee -a "${REPORT}"
else
  run_cmd "Top-level listing" ls -la "${SOURCE_HOME}"
  run_cmd "Depth-3 tree (dirs + compose + env)" find "${SOURCE_HOME}" -maxdepth 3 \( -type d -o -name 'docker-compose.yml' -o -name 'compose.yml' -o -name '.env' \) 2>/dev/null | sort
  run_cmd "Full permissions (first 400 lines)" bash -c "find '${SOURCE_HOME}' -printf '%M %u:%g %9s %TY-%Tm-%Td %TH:%TM %p\n' 2>/dev/null | head -400"
  find "${SOURCE_HOME}" -printf '%M %u:%g %9s %p\n' 2>/dev/null > "${OUT_DIR}/tree/full-permissions.txt" || true
  find "${SOURCE_HOME}" -maxdepth 2 -type d 2>/dev/null | sort > "${OUT_DIR}/tree/directories-l2.txt" || true
fi

# --- Grep hardcoded paths ---
section "4. HARDCODED PATHS (/home/${SOURCE_USER})"
run_cmd "References to source home" grep -r "/home/${SOURCE_USER}" "${SOURCE_HOME}" \
  --include='*.yml' --include='*.yaml' --include='*.env' --include='*.sh' --include='*.php' \
  2>/dev/null | head -200 || echo "None found"
run_cmd "References in cron" grep -r "/home/${SOURCE_USER}" /etc/cron* /var/spool/cron 2>/dev/null || echo "None in cron"

# --- Docker ---
section "5. DOCKER"
run_cmd "Docker version" docker version
run_cmd "Compose version" docker compose version
run_cmd "Running containers" docker ps -a --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}'
run_cmd "Networks" docker network ls
run_cmd "Volumes" docker volume ls
docker ps -a --format '{{.Names}}' 2>/dev/null | while read -r cname; do
  [[ -z "${cname}" ]] && continue
  {
    echo "### Container: ${cname}"
    docker inspect "${cname}" --format \
      'Image: {{.Config.Image}}
Status: {{.State.Status}}
Networks: {{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}
Mounts:'
    docker inspect "${cname}" --format '{{range .Mounts}}  {{.Type}} {{.Source}} -> {{.Destination}} ({{.Mode}})
{{end}}'
    echo ""
  } >> "${OUT_DIR}/docker/containers-detail.txt" 2>&1 || true
done
run_cmd "Compose projects (labels)" docker ps -a --filter 'label=com.docker.compose.project' --format '{{.Label "com.docker.compose.project"}}\t{{.Names}}' 2>/dev/null | sort -u || true

# --- Per-service folders ---
section "6. SERVICE FOLDERS"
for dir in "${SOURCE_HOME}"/*; do
  [[ -d "${dir}" ]] || continue
  name="$(basename "${dir}")"
  sub_report="${OUT_DIR}/tree/service-${name}.txt"
  {
    echo "=== ${dir} ==="
    ls -la "${dir}"
    echo ""
    echo "--- compose/env files ---"
    find "${dir}" \( -name 'docker-compose.yml' -o -name 'compose.yml' -o -name '.env' -o -name 'traefik.yml' \) 2>/dev/null
    echo ""
    echo "--- acme.json stat (if any) ---"
    find "${dir}" -name 'acme.json' -exec stat {} \; 2>/dev/null || true
  } > "${sub_report}" 2>&1
  copy_config_tree "${dir}" "${name}"
  echo "  Copied configs: ${name}" >> "${REPORT}"
done

# --- Traefik khusus ---
section "7. TRAEFIK SPOT CHECK"
TRAEFIK_DIR="${SOURCE_HOME}/traefik"
if [[ -d "${TRAEFIK_DIR}" ]]; then
  run_cmd "traefik/ listing" ls -la "${TRAEFIK_DIR}"
  run_cmd "traefik/data" ls -laR "${TRAEFIK_DIR}/data" 2>/dev/null || true
  run_cmd "acme.json metadata" stat "${TRAEFIK_DIR}/data/letsencrypt/acme.json" 2>/dev/null || echo "acme.json not found"
else
  echo "traefik/ tidak ada di ${SOURCE_HOME}" >> "${REPORT}"
fi

# --- WordPress sites ---
section "8. WORDPRESS / APP SITES"
WP_BASE="${SOURCE_HOME}/wordpress"
if [[ -d "${WP_BASE}" ]]; then
  run_cmd "wordpress subdirs" ls -la "${WP_BASE}"
  for site in "${WP_BASE}"/*; do
    [[ -d "${site}" ]] || continue
    sname="$(basename "${site}")"
    run_cmd "Site: ${sname}" ls -la "${site}"
  done
else
  echo "wordpress/ tidak ditemukan" >> "${REPORT}"
fi
APP_BASE="${SOURCE_HOME}/tarombo-app"
if [[ -d "${APP_BASE}" ]]; then
  run_cmd "tarombo-app listing" ls -laR "${APP_BASE}" 2>/dev/null | head -80
fi

# --- MySQL ---
section "9. MYSQL"
MYSQL_BASE="${SOURCE_HOME}/mysql"
if [[ -d "${MYSQL_BASE}" ]]; then
  run_cmd "mysql/ listing" ls -la "${MYSQL_BASE}"
  run_cmd "mysql data size" du -sh "${MYSQL_BASE}"/* 2>/dev/null || true
else
  echo "mysql/ tidak ditemukan" >> "${REPORT}"
fi

# --- Firewall & ports ---
section "10. NETWORK & FIREWALL"
run_cmd "Listening ports" ss -tlnp
run_cmd "UFW status" ufw status verbose 2>/dev/null || echo "UFW not active or not installed"
run_cmd "iptables NAT (first 30)" iptables -t nat -L -n -v 2>/dev/null | head -30 || true

# --- Cron ---
section "11. CRON & TIMERS"
run_cmd "Root crontab" crontab -l -u root 2>/dev/null || echo "empty"
run_cmd "aagit crontab" crontab -l -u "${SOURCE_USER}" 2>/dev/null || echo "empty"
run_cmd "System timers (active)" systemctl list-timers --no-pager 2>/dev/null | head -30 || true

# --- Other paths ---
section "12. OTHER DOCKER PATHS"
run_cmd "Compose outside home" find /opt /root /var/www /srv -name 'docker-compose.yml' 2>/dev/null | head -30 || echo "None"
run_cmd "Grep aagit in /etc" grep -r "${SOURCE_USER}" /etc 2>/dev/null | grep -vE 'shadow|passwd|group-' | head -30 || echo "None"

# --- Sizes (for migration planning) ---
section "13. DATA SIZES"
run_cmd "Home total size" du -sh "${SOURCE_HOME}" 2>/dev/null || true
run_cmd "Per top-level folder" du -sh "${SOURCE_HOME}"/* 2>/dev/null | sort -h || true

# --- Summary checklist ---
section "14. MIGRATION CHECKLIST (manual fill after review)"
cat >> "${REPORT}" <<'EOF'
[ ] Semua domain dicatat
[ ] Semua docker-compose.yml ada di configs/
[ ] Traefik acme.json ada (metadata .stat.txt)
[ ] User togaa sudah dibuat & SSH key tested
[ ] Tidak ada stack di luar /home/aagit yang terlewat
[ ] Backup snapshot provider (Nexosystems) dilakukan
[ ] Rencana downtime disetujui

DOMAIN / SITE LIST (isi manual):
  1. ___________________ → path: ___________________
  2. ___________________ → path: ___________________

EOF

# --- Package ---
ARCHIVE="/tmp/server-inventory-${STAMP}.tgz"
log "Membuat arsip ${ARCHIVE}"
tar -czf "${ARCHIVE}" -C "$(dirname "${OUT_DIR}")" "$(basename "${OUT_DIR}")"

log "Selesai."
echo ""
echo "========================================"
echo "  INVENTARIS SELESAI"
echo "========================================"
echo "  Folder : ${OUT_DIR}"
echo "  Laporan: ${REPORT}"
echo "  Arsip  : ${ARCHIVE}"
echo ""
echo "  Download ke PC (dari laptop):"
echo "    scp root@IP_SERVER:${ARCHIVE} ."
echo "  atau WinSCP: ambil file dari /tmp/"
echo ""
echo "  Kirim ke developer:"
echo "    - ${ARCHIVE}  (utama)"
echo "    - atau isi ${REPORT} jika kecil"
echo "========================================"
