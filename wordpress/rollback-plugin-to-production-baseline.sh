#!/usr/bin/env bash
# Kembalikan folder plugin di repo ke snapshot branch production-live (tanpa SSH).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_DST="${ROOT}/wordpress/ptsbi-premium"
BASELINE_REF="${PTPRM_BASELINE_REF:-production-live}"

cd "${ROOT}"
git fetch origin "${BASELINE_REF}" 2>/dev/null || true

if ! git rev-parse --verify "origin/${BASELINE_REF}" >/dev/null 2>&1; then
  echo "Branch origin/${BASELINE_REF} belum ada. Jalankan dulu workflow Sync Production Baseline di GitHub." >&2
  exit 1
fi

echo "==> Restore wordpress/ptsbi-premium dari origin/${BASELINE_REF} ..."
git checkout "origin/${BASELINE_REF}" -- wordpress/ptsbi-premium

VERSION_LINE="$(grep 'PTPRM_VERSION' "${PLUGIN_DST}/ptsbi-premium.php" | head -1 || true)"
echo "==> Selesai: ${VERSION_LINE:-}"
echo "Commit perubahan lalu deploy manual (workflow Deploy) jika ingin kirim ke server."
