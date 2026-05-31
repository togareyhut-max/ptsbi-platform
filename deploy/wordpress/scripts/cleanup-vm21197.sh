#!/usr/bin/env bash
# Pembersihan server vm21197: hapus artefak migrasi kotakita gagal + orphan Docker.
# Situs aktif (traefik, mysql, ptsbi, lpmpjk, kotakita, tarombo) TIDAK disentuh.
#
# Jalankan di server:
#   DRY_RUN=1 bash cleanup-vm21197.sh    # audit saja
#   DRY_RUN=0 bash cleanup-vm21197.sh    # benar-benar hapus
#
# Atau dari laptop:
#   scp deploy/wordpress/scripts/cleanup-vm21197.sh vm21197:~/
#   ssh vm21197 'DRY_RUN=1 bash ~/cleanup-vm21197.sh'
#   ssh vm21197 'DRY_RUN=0 bash ~/cleanup-vm21197.sh'
set -euo pipefail

DRY_RUN="${DRY_RUN:-1}"
# PURGE_UNKNOWN=1 untuk benar-benar menghapus container orphan di luar KEEP.
# Default 0 = hanya audit/list (mencegah hapus tak sengaja container penting).
PURGE_UNKNOWN="${PURGE_UNKNOWN:-0}"
HOME_DIR="/home/togaa"

# tarombo-postgres + wordpress-project-coba di-keep agar tidak terhapus loop orphan.
KEEP_CONTAINERS='traefik|mysql|wordpress-ptsbi|wordpress-lpmpjk|wordpress-kotakita|wordpress-project-coba|tarombo-web|tarombo-postgres'
KEEP_VOLUMES='mysqldata|wordpress_ptsbi|wordpress_lpmpjk|wordpress_kotakita|tarombo-app_pgdata|tarombo_pgdata|pgdata'

run() {
  if [[ "$DRY_RUN" == "1" ]]; then
    echo "[DRY-RUN] $*"
  else
    echo "[EXEC] $*"
    eval "$@"
  fi
}

echo "========== DISK BEFORE =========="
df -h "$HOME_DIR" /
du -sh "$HOME_DIR"/* 2>/dev/null | sort -hr | head -30

echo "========== HOME kotakita-* =========="
ls -la "$HOME_DIR"/kotakita* 2>/dev/null || true
find "$HOME_DIR" -maxdepth 2 -type d -name 'kotakita*' 2>/dev/null

echo "========== SQL / tar.gz di home =========="
find "$HOME_DIR" -maxdepth 3 \( -name '*.sql' -o -name '*.sql.gz' -o -name 'kotakita*.tar.gz' -o -name '*.tar.gz' \) 2>/dev/null

echo "========== wordpress .bak =========="
find "$HOME_DIR/wordpress" -name '*.bak' -o -name '*override*' 2>/dev/null || true

echo "========== DOCKER ps -a =========="
docker ps -a --format 'table {{.Names}}\t{{.Status}}\t{{.Image}}'

echo "========== ORPHAN CONTAINERS (not in KEEP list) =========="
docker ps -a --format '{{.Names}}' | grep -Ev "^(${KEEP_CONTAINERS})$" || true

echo "========== VOLUMES =========="
docker volume ls

echo "========== ORPHAN VOLUMES (heuristic) =========="
docker volume ls -q | while read -r v; do
  echo "$v" | grep -Eq "^(${KEEP_VOLUMES})$" && continue
  echo "$v" | grep -qi kotakita && echo "KOTAKITA-RELATED (review): $v"
  docker ps -a --filter "volume=$v" --format '{{.Names}}' | grep -q . || echo "NO CONTAINER MOUNT: $v"
done

echo "========== CLEANUP: kotakita-restore & kotakita-* dirs =========="
for p in "$HOME_DIR/kotakita-restore" "$HOME_DIR"/kotakita-*; do
  [[ -e "$p" ]] || continue
  [[ "$p" == "$HOME_DIR/wordpress" ]] && continue
  case "$p" in
    *docker-compose*) continue ;;
  esac
  run "rm -rf '$p'"
done

echo "========== CLEANUP: kotakita tar.gz & failed SQL dumps in home =========="
find "$HOME_DIR" -maxdepth 3 -type f \( -name 'kotakita*.tar.gz' -o -name '*.sql' -o -name '*.sql.gz' \) ! -path '*/wordpress/*' 2>/dev/null | while read -r f; do
  run "rm -f '$f'"
done

echo "========== CLEANUP: .bak in wordpress =========="
find "$HOME_DIR/wordpress" -type f -name '*.bak' 2>/dev/null | while read -r f; do
  run "rm -f '$f'"
done

echo "========== STOP/RM orphan containers (NOT in KEEP) =========="
if [[ "$PURGE_UNKNOWN" != "1" ]]; then
  echo "(audit saja — set PURGE_UNKNOWN=1 untuk benar-benar menghapus orphan)"
  docker ps -a --format '{{.Names}}' | grep -Ev "^(${KEEP_CONTAINERS})$" || echo "    (tidak ada)"
else
  docker ps -a --format '{{.Names}}' | grep -Ev "^(${KEEP_CONTAINERS})$" | while read -r c; do
    [[ -n "$c" ]] || continue
    run "docker rm -f '$c'"
  done
fi

echo "========== REMOVE unmounted kotakita volumes (failed attempts only) =========="
docker volume ls -q | while read -r v; do
  echo "$v" | grep -Eq "^(${KEEP_VOLUMES})$" && continue
  if echo "$v" | grep -qi kotakita; then
    if ! docker ps -a --filter "volume=$v" -q | grep -q .; then
      run "docker volume rm '$v'"
    else
      echo "[SKIP] volume $v still referenced"
    fi
  fi
done

echo "========== PRUNE dangling images only (NOT volumes) =========="
if [[ "$DRY_RUN" == "1" ]]; then
  echo "[DRY-RUN] docker image prune -f"
else
  docker image prune -f
fi

echo "========== DISK AFTER =========="
df -h "$HOME_DIR" /
du -sh "$HOME_DIR"/* 2>/dev/null | sort -hr | head -20

echo "========== RUNNING (must still be up) =========="
docker ps --format 'table {{.Names}}\t{{.Status}}'

echo ""
if [[ "$DRY_RUN" == "1" ]]; then
  echo "Mode DRY-RUN. Jalankan: DRY_RUN=0 bash $0"
else
  echo "Cleanup selesai."
fi
