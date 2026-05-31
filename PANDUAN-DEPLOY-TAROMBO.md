# Panduan Deploy & Backup Tarombo (tarombo.ptsbi.org)

Server **vm21197** (`/home/togaa`), database **PostgreSQL**, edge **Traefik bersama**.
Lokal pakai SQLite; server pakai Postgres — perbedaan ini **otomatis** ditangani lewat
`.env` + `docker-compose.server.yml` (memaksa `USE_SQLITE=0`). Jadi paket yang sama
jalan benar di server tanpa Anda mengubah kode.

---

## 0. Prinsip aman (yang TIDAK akan tersentuh)

Deploy ini hanya menyentuh **stack Tarombo** (`tarombo-web`, `tarombo-postgres`).
Yang berikut **tidak terganggu**:

- WordPress `ptsbi.org`, `lpmpjk.org`, `kotakita.net` (container & MySQL terpisah).
- Traefik & sertifikat HTTPS (hanya menambah/menyegarkan route `tarombo.ptsbi.org`).
- Data lama Tarombo: volume `pgdata` & folder `data/` **persisten** — update/rebuild
  container **tidak menghapus** data. Akun `pengurus` baru ditambahkan via migrasi
  otomatis (constraint `role`), bukan reset tabel.

> Aturan emas: **selalu backup dulu** (Bagian 2) sebelum deploy. Itu titik rollback Anda.

---

## 1. Siapkan paket (di komputer Windows)

```bat
PACK-UBUNTU.bat
```

Menghasilkan `deploy/tarombo-app.zip` (berisi app, `schema.sql` Postgres,
`docker-compose.server.yml`, dan `scripts/backup-tarombo.sh` + `restore-tarombo.sh`).

> `tarombo.db` (SQLite lokal) **tidak** ikut ke paket — server murni Postgres.

Upload ke server:

```bash
scp deploy/tarombo-app.zip vm21197:/home/togaa/
```

---

## 2. BACKUP kondisi live SEKARANG (wajib, sebelum apa pun)

Jika `tarombo.ptsbi.org` sudah pernah live, amankan dulu sebagai rollback:

```bash
ssh vm21197
cd /home/togaa/tarombo-app
bash scripts/backup-tarombo.sh
```

Hasil di `/home/togaa/tarombo-app/backups/`:

- `tarombo-db-<tanggal>.sql.gz` — dump database
- `tarombo-data-<tanggal>.tar.gz` — logo & upload
- `env-<tanggal>.bak` — salinan `.env`

> Kalau folder `scripts/backup-tarombo.sh` belum ada (deploy lama), jalankan manual:
> ```bash
> mkdir -p backups
> docker exec tarombo-postgres pg_dump -U tarombo tarombo_ptsbi | gzip > backups/tarombo-db-$(date +%F-%H%M).sql.gz
> tar czf backups/tarombo-data-$(date +%F-%H%M).tar.gz data
> ```

Simpan juga salinan ke luar server (opsional tapi disarankan):

```bash
scp vm21197:/home/togaa/tarombo-app/backups/tarombo-db-*.sql.gz ./backup-lokal/
```

---

## 3. Pasang paket baru di server

```bash
cd /home/togaa
# (Opsi aman) simpan folder lama sebagai cadangan kode, bukan timpa langsung:
[ -d tarombo-app ] && cp -a tarombo-app tarombo-app.prev-$(date +%F-%H%M)

unzip -o tarombo-app.zip -d /home/togaa
cd /home/togaa/tarombo-app
```

`unzip -o` menimpa berkas kode (app.py, template, dll.) **tanpa** menyentuh
`backups/`, `data/`, atau volume Postgres.

---

## 4. Konfigurasi `.env` (PostgreSQL)

Jika `.env` **sudah ada** dari deploy sebelumnya, **biarkan** (password & SECRET_KEY
harus tetap sama agar cocok dengan data lama). Hanya buat baru jika belum ada:

```bash
[ -f .env ] || cp .env.example .env
nano .env
```

Isi/periksa:

```
POSTGRES_USER=tarombo
POSTGRES_PASSWORD=<password-kuat-min-16>     # JANGAN ganti jika DB lama sudah pakai ini
POSTGRES_DB=tarombo_ptsbi
SECRET_KEY=<acak-min-32-karakter>
DATABASE_URL=postgresql://tarombo:<password-sama>@postgres:5432/tarombo_ptsbi
```

> PENTING: kalau Anda mengganti `POSTGRES_PASSWORD` padahal volume `pgdata` lama
> sudah berisi data dengan password berbeda, Postgres akan menolak koneksi.
> Untuk update, pertahankan kredensial yang sama dengan yang sedang live.

---

## 5. Deploy (Postgres + Traefik)

```bash
cd /home/togaa/tarombo-app
docker compose -f docker-compose.server.yml up -d --build
docker compose -f docker-compose.server.yml logs -f web
```

Yang terjadi: build image web → tunggu Postgres sehat → `init_db()` (CREATE TABLE
IF NOT EXISTS + migrasi role `pengurus`) → `seed_data()` (akun bawaan, sekali saja
lewat flag) → Gunicorn `:5000` → Traefik route `tarombo.ptsbi.org` (HTTPS).

Tekan `Ctrl+C` untuk berhenti melihat log (container tetap jalan).

---

## 6. Verifikasi

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}'
# harus ada: tarombo-web (Up), tarombo-postgres (Up, healthy)

curl -I https://tarombo.ptsbi.org           # 200/301/302
```

Lalu di browser **https://tarombo.ptsbi.org**:

- Login `admin@ptsbi.org` / `12345678` (ganti password!).
- Coba **Profil** anggota → simpan data (isi nama ayah & ibu) → pastikan tersimpan.
- Pastikan `ptsbi.org`, `lpmpjk.org`, `kotakita.net` tetap normal.

---

## 7. Rollback (jika update bermasalah)

Pakai backup dari Bagian 2:

```bash
cd /home/togaa/tarombo-app
bash scripts/restore-tarombo.sh backups/tarombo-db-<tanggal>.sql.gz backups/tarombo-data-<tanggal>.tar.gz
```

Skrip akan: stop web → drop & buat ulang DB → impor dump → pulihkan `data/` →
nyalakan web. (Ketik `YA` saat konfirmasi.)

Jika perlu balik ke kode lama:

```bash
cd /home/togaa
rm -rf tarombo-app && mv tarombo-app.prev-<tanggal> tarombo-app
cd tarombo-app && docker compose -f docker-compose.server.yml up -d --build
```

---

## 8. Backup rutin setelah live (otomatis)

Pasang cron harian (mis. 02:30):

```bash
crontab -e
```

Tambah baris:

```
30 2 * * * cd /home/togaa/tarombo-app && /usr/bin/bash scripts/backup-tarombo.sh >> /home/togaa/tarombo-app/backups/cron.log 2>&1
```

Skrip menyimpan 14 backup terbaru per jenis (atur via `KEEP=...`). Sesekali salin
folder `backups/` ke penyimpanan lain (laptop / cloud) agar aman dari kegagalan disk.

---

## 9. Catatan perbedaan lokal vs server

| Aspek | Lokal (Windows) | Server (vm21197) |
|-------|-----------------|------------------|
| Database | SQLite `tarombo.db` | PostgreSQL (`tarombo-postgres`) |
| Env | `USE_SQLITE=1` | `USE_SQLITE=0` + `DATABASE_URL` (dipaksa compose) |
| Schema | `schema_sqlite.sql` | `schema.sql` |
| Jalan | `START_APP.bat` → `:5000` | `docker compose -f docker-compose.server.yml` + Traefik |
| Akun | sama: 4 akun, `12345678` | sama: 4 akun, `12345678` |

Kode memilih jalur SQLite/Postgres berdasarkan env, jadi **tidak ada perubahan
kode** yang perlu Anda lakukan untuk berpindah lokal → server.
