#!/usr/bin/env bash
# Cek ketersediaan layanan ptsbi.org (HTTP) — jalankan dari laptop/CI.
set -euo pipefail

check_url() {
  local url="$1"
  local code
  code="$(curl -sI -m 20 -o /dev/null -w '%{http_code}' "$url" || echo "000")"
  if [[ "$code" =~ ^2 ]]; then
    echo "OK  $code  $url"
  else
    echo "DOWN $code $url"
  fi
}

echo "=== HTTP health ==="
check_url "https://ptsbi.org/"
check_url "https://ptsbi.org/panel-pengurus/"
check_url "https://ptsbi.org/ptsbi-pusat/"
check_url "https://ptsbi.org/rumah-anggota/"
check_url "https://tarombo.ptsbi.org/"
check_url "https://ptsbi.org/wp-json/"

echo ""
echo "=== Plugin shortcode (harus HTML, bukan teks [ptprm_...]) ==="
panel="$(curl -sL -m 25 "https://ptsbi.org/panel-pengurus/" || true)"
if echo "$panel" | grep -q '<p>\[ptprm_admin_portal\]</p>'; then
  echo "FAIL panel-pengurus: shortcode tidak diproses — plugin ptsbi-premium tidak aktif atau belum ter-deploy"
else
  echo "OK   panel-pengurus: shortcode terproses atau halaman login"
fi

pusat="$(curl -sL -m 25 "https://ptsbi.org/ptsbi-pusat/" || true)"
if echo "$pusat" | grep -q 'ptprm-board-line\|ptprm-board-featured'; then
  echo "OK   ptsbi-pusat: daftar pengurus tampil"
elif echo "$pusat" | grep -q '\[ptprm_board'; then
  echo "FAIL ptsbi-pusat: shortcode mentah — deploy + aktifkan plugin"
else
  echo "WARN ptsbi-pusat: tidak ada markup ptprm-board (cek manual)"
fi
