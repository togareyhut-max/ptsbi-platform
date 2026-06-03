#!/usr/bin/env bash
# Deploy ptsbi-premium ke server production (ptsbi.org).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_SRC="${ROOT}/wordpress/ptsbi-premium"
SSH_HOST="${PTPRM_SSH_HOST:-togaa@5.175.245.78}"
WP_CONTAINER="${PTPRM_WP_CONTAINER:-wordpress-ptsbi}"
REMOTE_DIR="/home/togaa/ptsbi-premium"
PLUGIN_SLUG="ptsbi-premium"
PLUGIN_FILE="${PLUGIN_SLUG}/ptsbi-premium.php"
SSH_ID="${HOME}/.ssh/ptprm_deploy_key"
DEPLOY_HOST="${DEPLOY_HOST:-5.175.245.78}"

if [[ ! -f "${PLUGIN_SRC}/ptsbi-premium.php" ]]; then
  echo "Plugin tidak ditemukan: ${PLUGIN_SRC}" >&2
  exit 1
fi

LOCAL_VER="$(grep -m1 "define( 'PTPRM_VERSION'" "${PLUGIN_SRC}/ptsbi-premium.php" | sed -E "s/.*'([0-9.]+)'.*/\1/")"
echo "==> Versi sumber deploy: ${LOCAL_VER}"

setup_ssh_key() {
  if [[ -z "${SSH_PRIVATE_KEY:-}" ]]; then
    return 1
  fi
  mkdir -p "${HOME}/.ssh"
  chmod 700 "${HOME}/.ssh"
  if echo "${SSH_PRIVATE_KEY}" | base64 -d > "${SSH_ID}" 2>/dev/null; then
    :
  else
    printf '%s\n' "${SSH_PRIVATE_KEY}" > "${SSH_ID}"
  fi
  chmod 600 "${SSH_ID}"
  if ! ssh-keygen -y -f "${SSH_ID}" >/dev/null 2>&1; then
    echo "SSH_PRIVATE_KEY tidak valid." >&2
    rm -f "${SSH_ID}"
    return 1
  fi
  ssh-keygen -f "${HOME}/.ssh/known_hosts" -R "${DEPLOY_HOST}" 2>/dev/null || true
  ssh-keyscan -p "${PTPRM_SSH_PORT:-22}" -H "${DEPLOY_HOST}" >> "${HOME}/.ssh/known_hosts" 2>/dev/null || true
  return 0
}

SSH_PORT="${PTPRM_SSH_PORT:-22}"
SSH_OPTS=(-p "${SSH_PORT}" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)
if setup_ssh_key; then
  SSH_OPTS+=(-i "${SSH_ID}" -o IdentitiesOnly=yes)
  echo "==> Memakai kunci dari secret SSH_PRIVATE_KEY"
else
  echo "==> Peringatan: SSH_PRIVATE_KEY tidak diset; memakai kunci default ~/.ssh" >&2
  ssh-keygen -f "${HOME}/.ssh/known_hosts" -R "${DEPLOY_HOST}" 2>/dev/null || true
  ssh-keyscan -p "${SSH_PORT}" -H "${DEPLOY_HOST}" >> "${HOME}/.ssh/known_hosts" 2>/dev/null || true
fi

echo "==> Upload ke ${SSH_HOST}:${REMOTE_DIR} ..."
if command -v rsync >/dev/null 2>&1; then
  rsync -az --delete -e "ssh ${SSH_OPTS[*]}" "${PLUGIN_SRC}/" "${SSH_HOST}:${REMOTE_DIR}/"
else
  ssh "${SSH_OPTS[@]}" "${SSH_HOST}" "mkdir -p ${REMOTE_DIR}"
  scp "${SSH_OPTS[@]}" -r "${PLUGIN_SRC}/." "${SSH_HOST}:${REMOTE_DIR}/"
fi

echo "==> Salin ke container, aktifkan plugin, cek layanan ..."
ssh "${SSH_OPTS[@]}" "${SSH_HOST}" bash -s <<REMOTE
set -euo pipefail
PLUGIN_PATH="/var/www/html/wp-content/plugins/${PLUGIN_SLUG}"
docker exec ${WP_CONTAINER} rm -rf "\${PLUGIN_PATH}"
docker exec ${WP_CONTAINER} mkdir -p "\${PLUGIN_PATH}"
docker cp ${REMOTE_DIR}/. ${WP_CONTAINER}:"\${PLUGIN_PATH}/"
docker exec ${WP_CONTAINER} chown -R www-data:www-data "\${PLUGIN_PATH}"
docker exec ${WP_CONTAINER} grep -m1 "PTPRM_VERSION" "\${PLUGIN_PATH}/ptsbi-premium.php" || true

if docker exec ${WP_CONTAINER} which wp >/dev/null 2>&1; then
  docker exec ${WP_CONTAINER} wp plugin activate ${PLUGIN_SLUG} --allow-root 2>/dev/null || true
  docker exec ${WP_CONTAINER} wp cache flush --allow-root 2>/dev/null || true
else
  docker exec ${WP_CONTAINER} php -r '
require "/var/www/html/wp-load.php";
\$f = "${PLUGIN_FILE}";
\$a = get_option("active_plugins", []);
if (!is_array(\$a)) { \$a = []; }
if (!in_array(\$f, \$a, true)) {
  \$a[] = \$f;
  sort(\$a);
  update_option("active_plugins", \$a);
  echo "PLUGIN_ACTIVATED=1\n";
} else {
  echo "PLUGIN_ALREADY_ACTIVE=1\n";
}
if (function_exists("wp_cache_flush")) { wp_cache_flush(); }
' 2>/dev/null || echo "PLUGIN_ACTIVATE_FAILED=1"
fi

docker exec ${WP_CONTAINER} php -r '
require "/var/www/html/wp-load.php";
echo is_plugin_active("${PLUGIN_FILE}") ? "PLUGIN_ACTIVE=yes\n" : "PLUGIN_ACTIVE=no\n";
' 2>/dev/null || true


docker exec ${WP_CONTAINER} php -r '
require "/var/www/html/wp-load.php";
if (function_exists("wp_cache_flush")) { wp_cache_flush(); }
if (has_action("litespeed_purge_all")) { do_action("litespeed_purge_all"); }
if (function_exists("opcache_reset")) { opcache_reset(); }
echo "CACHE_OPCACHE_PURGED=1
";
' 2>/dev/null || true
docker exec ${WP_CONTAINER} apachectl -k graceful 2>/dev/null || true

for c in ${WP_CONTAINER} traefik tarombo-web mysql; do
  if docker ps --format '{{.Names}}' | grep -qx "\$c"; then
    echo "CONTAINER_UP=\$c"
  else
    echo "CONTAINER_DOWN=\$c"
    case "\$c" in
      ${WP_CONTAINER}) cd /home/togaa/wordpress 2>/dev/null && docker compose up -d wordpress-ptsbi 2>/dev/null || true ;;
      traefik) cd /home/togaa/traefik 2>/dev/null && docker compose up -d 2>/dev/null || true ;;
      tarombo-web) cd /home/togaa/tarombo-app 2>/dev/null && docker compose up -d 2>/dev/null || true ;;
      mysql) cd /home/togaa/wordpress 2>/dev/null && docker compose up -d mysql 2>/dev/null || true ;;
    esac
  fi
done
REMOTE


echo "VERIFY_SUBPAGE_BOARD=$(docker exec ${WP_CONTAINER} grep -c board_html ${PLUGIN_PATH}/templates/subpage.php 2>/dev/null || echo 0)"
docker exec ${WP_CONTAINER} php -r 'require "/var/www/html/wp-load.php"; echo function_exists("ptprm_board_default_catalog") ? "CATALOG_FN=yes\n" : "CATALOG_FN=no\n";' 2>/dev/null || true

echo "==> Selesai deploy v${LOCAL_VER}"
