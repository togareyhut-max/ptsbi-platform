#!/usr/bin/env bash
# Bandingkan versi & hash asset plugin: repo vs ptsbi.org live.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN="${ROOT}/wordpress/ptsbi-premium"
CSS="${PLUGIN}/assets/css/frontend.css"

EXPECTED="$(grep -oP "define\(\s*'PTPRM_VERSION',\s*'\K[^']+" "${PLUGIN}/ptsbi-premium.php" | head -1)"
LIVE_VER="$(curl -fsSL 'https://ptsbi.org/panel-pengurus/' | grep -oP 'ptsbi-premium/assets/css/frontend\.css\?ver=\K[0-9.]+' | head -1 || true)"

echo "Repo versi   : ${EXPECTED}"
echo "Live versi   : ${LIVE_VER:-?}"

LOCAL_HASH="$(sha256sum "${CSS}" | awk '{print $1}')"
LIVE_HASH="$(curl -fsSL "https://ptsbi.org/wp-content/plugins/ptsbi-premium/assets/css/frontend.css?ver=${EXPECTED}" | sha256sum | awk '{print $1}')"

echo "Repo CSS hash: ${LOCAL_HASH}"
echo "Live CSS hash: ${LIVE_HASH}"

if [[ "${EXPECTED}" != "${LIVE_VER}" ]]; then
  echo "GAGAL: versi tidak cocok." >&2
  exit 1
fi
if [[ "${LOCAL_HASH}" != "${LIVE_HASH}" ]]; then
  echo "GAGAL: hash CSS tidak cocok." >&2
  exit 1
fi

echo "OK: repo dan website live selaras (v${EXPECTED})."
