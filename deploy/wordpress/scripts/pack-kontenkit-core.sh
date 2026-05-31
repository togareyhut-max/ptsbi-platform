#!/usr/bin/env bash
# Buat ZIP plugin KontenKit Core untuk upload WordPress
#   bash pack-kontenkit-core.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/plugins/kontenkit-core"
OUT="${OUT:-$HOME/kontenkit-core.zip}"
[[ -d "$SRC" ]] || { echo "ERROR: $SRC tidak ada"; exit 1; }
rm -f "$OUT"
(cd "$SRC/.." && zip -qr "$OUT" kontenkit-core)
ls -lh "$OUT"
echo "Upload: Plugins -> Add New -> Upload Plugin -> $OUT"
