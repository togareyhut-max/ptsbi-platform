# Lepas ban IP — SSH / WinSCP timeout

**Timeout** (bukan "Connection refused") sering berarti paket **dibuang firewall** atau server tidak terjangkau — salah satunya IP Anda kena ban.

**Cerber** (plugin WordPress) hanya memblokir **HTTP** (login wp-admin), **bukan** SSH/WinSCP.

---

## Langkah 0 — Cek dari PC (PowerShell)

```powershell
Test-NetConnection 5.175.245.78 -Port 22
```

| Hasil | Artinya |
|-------|---------|
| `TcpTestSucceeded : True` | Port 22 terbuka — masalah mungkin user/key SSH, bukan ban IP |
| `TcpTestSucceeded : False` / timeout | Firewall, ban IP, server mati, atau IP salah |

Cek IP publik Anda (Wi‑Fi yang dipakai untuk SSH):

```powershell
(Invoke-WebRequest -Uri "https://ifconfig.me" -UseBasicParsing).Content
```

Buka di browser: https://ptsbi.org — kalau website **jalan** tapi SSH **timeout**, sangat mungkin **hanya SSH** yang diblokir (fail2ban / UFW).

---

## Anda tidak bisa SSH? Masuk lewat konsol VPS

1. Login **panel Nexosystems**
2. Buka **VNC / KVM / Console** untuk vm21197
3. Login sebagai **root** (atau user lokal) — ini tidak lewat internet dari IP Anda

Di konsol:

```bash
# Ganti dengan IP dari ifconfig.me di PC Anda
sudo bash /root/unban-ip.sh 203.0.113.50
```

Upload `deploy/server/unban-ip.sh` lewat panel file manager, atau paste isinya.

---

## Manual (di konsol server)

### fail2ban (paling sering untuk SSH)

```bash
sudo fail2ban-client status
sudo fail2ban-client status sshd
sudo fail2ban-client set sshd unbanip IP_ANDA
```

Semua jail:

```bash
sudo fail2ban-client unban IP_ANDA
```

### UFW

```bash
sudo ufw status numbered
# Jika ada deny untuk IP Anda:
sudo ufw delete NOMOR_RULE
```

### hosts.deny

```bash
grep IP_ANDA /etc/hosts.deny
sudo sed -i '/IP_ANDA/d' /etc/hosts.deny
```

---

## Setelah unban — tes SSH

```bash
ssh -v togaa@5.175.245.78
```

WinSCP:

| Field | Nilai |
|-------|--------|
| Host | `5.175.245.78` |
| User | `togaa` (bukan root, bukan aagit) |
| Port | `22` |
| Protokol | SFTP |

Root SSH biasanya **ditolak** setelah hardening (`PermitRootLogin no`).

---

## Alternatif tanpa konsol

- **Hotspot HP** (IP berbeda) → coba SSH lagi; kalau masuk, jalankan `unban-ip.sh` untuk IP rumah/kantor.
- **Tanya Nexosystems** untuk buka konsol atau whitelist IP di firewall provider.

---

## Cegah kena ban lagi

- Pakai **SSH key**, jangan brute-force password.
- Jangan banyak kali salah password / user (`aagit` sudah tidak ada → pakai `togaa`).
- Opsional: whitelist IP Anda di fail2ban (`/etc/fail2ban/jail.local`).

---

*Server: vm21197 · IPv4: 5.175.245.78 · User: togaa*
