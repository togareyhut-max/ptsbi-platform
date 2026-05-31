#!/usr/bin/env bash
# Hapus sisa deploy GAGAL: wordpress-belajar saja.
# TIDAK menyentuh: kotakita (sudah OK), ptsbi, lpmpjk, tarombo, traefik, mysql
#
#   DRY_RUN=1 bash /home/togaa/cleanup-sandbox-failed.sh   # audit
#   bash /home/togaa/cleanup-sandbox-failed.sh             # hapus
set -euo pipefail

DRY_RUN="${DRY_RUN:-0}"
# PURGE_UNKNOWN=1 untuk benar-benar menghapus container di luar daftar KEEP.
# Default 0 = hanya audit (mencegah hapus tak sengaja, mis. tarombo-postgres / project-coba).
PURGE_UNKNOWN="${PURGE_UNKNOWN:-0}"
WP_DIR="/home/togaa/wordpress"
HOME_DIR="/home/togaa"
MYSQL_COMPOSE="/home/togaa/mysql/docker-compose.yml"

# tarombo-postgres WAJIB di-keep (berisi database silsilah). project-coba sandbox
# tetap dipertahankan agar tidak terhapus oleh skrip "belajar saja" ini.
KEEP_REGEX='^(traefik|mysql|wordpress-ptsbi|wordpress-lpmpjk|wordpress-kotakita|wordpress-project-coba|tarombo-web|tarombo-postgres)$'

run() {
  if [[ "$DRY_RUN" == "1" ]]; then
    echo "[DRY-RUN] $*"
  else
    echo "[EXEC] $*"
    eval "$@"
  fi
}

read_mysql_root() {
  if [[ -n "${MYSQL_ROOT:-}" ]]; then
    echo "$MYSQL_ROOT"
    return
  fi
  if docker ps --format '{{.Names}}' | grep -qx mysql; then
    local p
    p=$(docker exec mysql printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true)
    [[ -n "$p" ]] && { echo "$p"; return; }
  fi
  if [[ -f "$MYSQL_COMPOSE" ]]; then
    grep -m1 'MYSQL_ROOT_PASSWORD:' "$MYSQL_COMPOSE" | sed -E "s/.*MYSQL_ROOT_PASSWORD:[[:space:]]*['\"]?([^'\"]+)['\"]?.*/\1/" | sed 's/\$\$/\$/g'
    return
  fi
  echo ""
}

strip_belajar_from_compose() {
  [[ -f "$WP_DIR/docker-compose.yml" ]] || return 0
  if ! grep -q "wordpress-belajar:" "$WP_DIR/docker-compose.yml" 2>/dev/null; then
    return 0
  fi
  run "cp -a '$WP_DIR/docker-compose.yml' '$WP_DIR/docker-compose.yml.bak-cleanup-$(date +%Y%m%d-%H%M%S)'"
  if [[ "$DRY_RUN" == "1" ]]; then
    echo "[DRY-RUN] hapus blok wordpress-belajar dari compose utama"
    return 0
  fi
  python3 << 'PY'
import re
path = "/home/togaa/wordpress/docker-compose.yml"
text = open(path, encoding="utf-8").read()
text = re.sub(
    r"\n  wordpress-belajar:.*?(?=\n  wordpress-|\n\nnetworks:|\nnetworks:)",
    "",
    text,
    count=1,
    flags=re.S,
)
text = re.sub(r"\n  wordpress_belajar:\n", "\n", text)
open(path, "w", encoding="utf-8").write(text)
print("    Blok wordpress-belajar di compose utama dihapus.")
PY
}

compose_up_keep_kotakita() {
  local cmd="cd '$WP_DIR' && docker compose -f docker-compose.yml"
  [[ -f "$WP_DIR/docker-compose.kotakita.yml" ]] && cmd="$cmd -f docker-compose.kotakita.yml"
  cmd="$cmd up -d --remove-orphans"
  run "$cmd"
}

echo "=============================================="
echo " BERSIHKAN sandbox BELAJAR saja"
echo " Kotakita TIDAK disentuh"
echo " Mode: $([ \"$DRY_RUN\" == \"1\" ] && echo AUDIT || echo HAPUS)"
echo "=============================================="

echo ""
echo "==> Harus tetap jalan:"
docker ps --format '  {{.Names}}  {{.Status}}' | grep -E 'traefik|mysql|wordpress-ptsbi|wordpress-lpmpjk|wordpress-kotakita|tarombo-web' || {
  echo "PERINGATAN: kotakita/ptsbi/lpmpjk — cek manual!"
}

echo ""
echo "==> Target hapus: wordpress-belajar + wp_belajar saja"

if [[ -f "$WP_DIR/docker-compose.belajar.yml" ]]; then
  run "cd '$WP_DIR' && docker compose -f docker-compose.yml -f docker-compose.belajar.yml stop wordpress-belajar 2>/dev/null || true"
  run "cd '$WP_DIR' && docker compose -f docker-compose.yml -f docker-compose.belajar.yml rm -f wordpress-belajar 2>/dev/null || true"
fi

run "docker stop wordpress-belajar 2>/dev/null || true"
run "docker rm -f wordpress-belajar 2>/dev/null || true"

for v in wordpress_wordpress_belajar wordpress_belajar; do
  run "docker volume rm '$v' 2>/dev/null || true"
done

if [[ "$DRY_RUN" != "1" ]]; then
  docker volume ls -q | while read -r v; do
    echo "$v" | grep -qi belajar || continue
    docker volume rm "$v" 2>/dev/null && echo "    Volume dihapus: $v" || true
  done
fi

run "rm -f '$WP_DIR/docker-compose.belajar.yml'"
run "rm -f '$WP_DIR/.env.belajar'"
run "find '$WP_DIR' -maxdepth 1 -type f -name '*belajar*' -delete 2>/dev/null || true"

strip_belajar_from_compose

MYSQL_ROOT="$(read_mysql_root)"
if [[ -n "$MYSQL_ROOT" ]]; then
  echo ""
  echo "==> Hapus database wp_belajar..."
  if [[ "$DRY_RUN" == "1" ]]; then
    echo "[DRY-RUN] DROP DATABASE wp_belajar; DROP USER belajar"
  else
    docker exec -e MYSQL_PWD="$MYSQL_ROOT" mysql mysql -uroot -e "
DROP DATABASE IF EXISTS wp_belajar;
DROP USER IF EXISTS 'belajar'@'%';
FLUSH PRIVILEGES;
"
  fi
fi

for p in "$HOME_DIR/belajar-restore" "$HOME_DIR"/belajar-*; do
  [[ -e "$p" ]] || continue
  run "rm -rf '$p'"
done

run "find '$HOME_DIR' -maxdepth 2 -type f \\( -name '*belajar*.tar.gz' -o -name '*belajar*.sql' \\) -delete 2>/dev/null || true"

echo ""
echo "==> Container lain (bukan daftar KEEP)..."
if [[ "$DRY_RUN" == "1" || "$PURGE_UNKNOWN" != "1" ]]; then
  echo "    (audit saja — set PURGE_UNKNOWN=1 untuk menghapus):"
  docker ps -a --format '{{.Names}}' | grep -Ev "$KEEP_REGEX" || echo "    (tidak ada)"
else
  docker ps -a --format '{{.Names}}' | grep -Ev "$KEEP_REGEX" | while read -r c; do
    [[ -n "$c" ]] || continue
    docker rm -f "$c" 2>/dev/null && echo "    Container dihapus: $c" || true
  done
fi

echo ""
echo "==> Refresh compose (ptsbi + lpmpjk + kotakita)..."
compose_up_keep_kotakita

run "docker image prune -f 2>/dev/null || true"

echo ""
echo "=============================================="
docker ps --format 'table {{.Names}}\t{{.Status}}'
echo ""
echo "Harus Up: traefik, mysql, wordpress-ptsbi, wordpress-lpmpjk, wordpress-kotakita, tarombo-web"
echo "Harus hilang: wordpress-belajar"
echo ""
echo "Kotakita: minta teman set DNS A @ dan www -> 5.175.245.78"
echo "Lalu tes https://kotakita.net"
echo ""
if [[ "$DRY_RUN" == "1" ]]; then
  echo "Audit selesai. Hapus: bash $0"
fi
