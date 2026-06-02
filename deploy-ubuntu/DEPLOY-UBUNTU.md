# Deploy Tarombo PTSBI — Ubuntu 24.04

Paket ini berisi aplikasi lengkap + PostgreSQL 16 via Docker. Cocok untuk server Ubuntu 24 LTS.

## Dua mode deploy (pilih salah satu)

| Mode | Lokasi | Compose | Edge / HTTPS |
|------|--------|---------|--------------|
| **A. Production vm21197 (dipakai sekarang)** | `/home/togaa/tarombo-app` | `docker-compose.server.yml` | Traefik bersama (label `Host(tarombo.ptsbi.org)`) |
| **B. Standalone generik** | `/opt/tarombo-app` | `docker-compose.yml` | Nginx + Certbot (port `127.0.0.1:5000`) |

> Server PTSBI yang aktif memakai **Mode A**. Jangan campur keduanya pada host yang
> sama (nama container `tarombo-web`/`tarombo-postgres` akan bentrok).

## Isi paket

| Komponen | Keterangan |
|----------|------------|
| `app.py` + `services/` | Aplikasi Flask (silsilah, admin, approve, pohon) |
| `schema.sql` | Skema PostgreSQL |
| `data/uploads/` | Logo kustom PNG (persisten, volume Docker) |
| `docker-compose.yml` | PostgreSQL + web (Gunicorn) |
| `deploy/nginx-tarombo.conf.example` | Contoh reverse proxy |

## Prasyarat server

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-plugin
sudo usermod -aG docker $USER
# logout/login ulang agar grup docker aktif
```

## Langkah deploy

### Mode A — Production vm21197 (Traefik, dipakai sekarang)

```bash
# 1. Upload tarombo-app.zip ke server, lalu:
unzip -o tarombo-app.zip -d /home/togaa
cd /home/togaa/tarombo-app

# 2. Environment
cp .env.example .env
nano .env
# Wajib ganti: POSTGRES_PASSWORD, SECRET_KEY (USE_SQLITE=0)

# 3. Build & jalankan via Traefik
docker compose -f docker-compose.server.yml up -d --build
docker compose -f docker-compose.server.yml logs -f web
```

Buka: `https://tarombo.ptsbi.org` (Traefik + Let's Encrypt menangani HTTPS).

### Mode B — Standalone generik (Nginx)

```bash
sudo mkdir -p /opt && sudo chown $USER:$USER /opt
unzip -o tarombo-app.zip -d /opt
cd /opt/tarombo-app
cp .env.example .env && nano .env
docker compose up -d --build      # mengikat 127.0.0.1:5000
docker compose logs -f web
```

Buka: `http://IP-SERVER:5000` (atau lewat Nginx di bawah).

## Akun awal (seed)

Saat database pertama kali dibuat, empat akun bawaan dibuat dengan **password `12345678`**:

| Peran | Email | Password |
|-------|-------|----------|
| Admin | admin@ptsbi.org | 12345678 |
| Pengurus | pengurus@ptsbi.org | 12345678 |
| Anggota | anggota@ptsbi.org | 12345678 |
| Developer | developer@ptsbi.org | 12345678 |

**Ganti password segera setelah login pertama.** (Reset password hanya diterapkan
sekali; perubahan password Anda tidak akan ditimpa pada deploy berikutnya.)

## Logo situs (PNG)

1. Login admin → **Logo Situs** (atau `/admin/logo`)
2. Unggah berkas **PNG** (maks. 2 MB)
3. Logo disimpan di `data/uploads/site-logo.png` (volume `./data` — tidak hilang saat rebuild container)

## Nginx + HTTPS (opsional)

```bash
sudo cp deploy/nginx-tarombo.conf.example /etc/nginx/sites-available/tarombo
sudo ln -s /etc/nginx/sites-available/tarombo /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d tarombo.ptsbi.org
```

`docker-compose.yml` mengikat `127.0.0.1:5000` — hanya Nginx yang terpapar publik.

## Backup database

```bash
docker exec tarombo-postgres pg_dump -U tarombo tarombo_ptsbi > backup_$(date +%F).sql
```

Backup logo + uploads:

```bash
tar czf tarombo-data_$(date +%F).tar.gz data/
```

## Panel admin — sinkron pohon

Admin → **Sinkron Tarombo** / **Gabung & Bersihkan Pohon**

## Troubleshooting

| Gejala | Solusi |
|--------|--------|
| web restart loop | `docker compose logs web` — cek `DATABASE_URL` di `.env` |
| /tarombo error 500 | Admin → Sinkron Tarombo |
| logo tidak berubah | Hard refresh browser (Ctrl+F5); cek `data/uploads/site-logo.png` |
| port 5000 tertutup | `ss -lntp \| grep 5000` |

## Opsional: Watchdog auto-heal (Mode A)

Jika ingin server otomatis “menghidupkan kembali” Tarombo saat API/DB terindikasi down, aktifkan systemd timer berikut (disertakan di paket deploy):

```bash
cd /home/togaa/tarombo-app
sudo cp deploy/systemd/tarombo-watchdog.service /etc/systemd/system/
sudo cp deploy/systemd/tarombo-watchdog.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now tarombo-watchdog.timer
sudo systemctl status tarombo-watchdog.timer --no-pager
```

Tes manual:

```bash
sudo systemctl start tarombo-watchdog.service
sudo journalctl -u tarombo-watchdog.service -n 50 --no-pager
```

## Bangun ulang paket (dari Windows dev)

Jalankan `PACK-UBUNTU.bat` di folder pengembangan — menghasilkan folder siap upload.
