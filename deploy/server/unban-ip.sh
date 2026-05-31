#!/usr/bin/env bash
# unban-ip.sh — lepas blokir IP (fail2ban, UFW, hosts.deny, iptables)
# Jalankan di server lewat KONSOL Nexosystems (bukan SSH dari PC yang kena ban):
#   sudo bash unban-ip.sh 203.0.113.50
# Atau tanpa argumen: coba deteksi dari log fail2ban
set -euo pipefail

TARGET_IP="${1:-}"

if [[ -z "$TARGET_IP" ]]; then
  echo "Usage: sudo $0 IP_ANDA"
  echo "Contoh: sudo $0 203.0.113.50"
  echo ""
  echo "Cek IP publik Anda di https://ifconfig.me dari PC/HP (jaringan yang dipakai SSH)."
  exit 1
fi

echo "=== Unban IP: $TARGET_IP ==="

# --- fail2ban ---
if command -v fail2ban-client >/dev/null 2>&1; then
  echo ""
  echo "--- fail2ban ---"
  for jail in $(fail2ban-client status 2>/dev/null | sed -n 's/.*Jail list:[[:space:]]*//p' | tr ',' ' '); do
    jail=$(echo "$jail" | xargs)
    [[ -z "$jail" ]] && continue
    if fail2ban-client status "$jail" 2>/dev/null | grep -q "$TARGET_IP"; then
      echo "    Unban $TARGET_IP dari jail: $jail"
      fail2ban-client set "$jail" unbanip "$TARGET_IP" 2>/dev/null || \
        fail2ban-client unban "$TARGET_IP" 2>/dev/null || true
    fi
  done
  # fallback lama
  fail2ban-client unban "$TARGET_IP" 2>/dev/null || true
else
  echo "    fail2ban tidak terpasang — lewati"
fi

# --- UFW ---
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -qi active; then
  echo ""
  echo "--- UFW (aturan yang menyebut IP) ---"
  ufw status numbered | grep -F "$TARGET_IP" || echo "    Tidak ada rule khusus untuk IP ini"
  # Hapus manual jika ada: ufw status numbered lalu ufw delete N
fi

# --- hosts.deny ---
if [[ -f /etc/hosts.deny ]] && grep -qF "$TARGET_IP" /etc/hosts.deny 2>/dev/null; then
  echo ""
  echo "--- /etc/hosts.deny ---"
  sed -i "/${TARGET_IP//./\\.}/d" /etc/hosts.deny
  echo "    Baris IP dihapus"
fi

# --- iptables / nft ---
echo ""
echo "--- iptables (DROP/REJECT untuk IP) ---"
iptables -L INPUT -n -v 2>/dev/null | grep -F "$TARGET_IP" || echo "    Tidak terlihat di INPUT"

echo ""
echo "=== Selesai ==="
echo "Tes dari PC: ssh -v togaa@5.175.245.78"
echo "WinSCP: host 5.175.245.78, user togaa, port 22, protokol SFTP"
