# Panduan edit website (setelah migrasi ke `/home/togaa`)

Server: **vm21197** · Domain: **lpmpjk.org**, **ptsbi.org**, **tarombo.ptsbi.org**

---

## Ringkas: kenapa edit di folder salah tidak kelihatan

| Layanan | Jangan edit hanya di | Edit di |
|---------|----------------------|---------|
| WordPress | `/home/togaa/wordpress/` (hanya compose) | Volume Docker (lihat bawah) atau WP Admin |
| Tarombo | — | `/home/togaa/tarombo-app/` + **`docker compose up -d --build`** |
| Traefik / SSL | — | `/home/togaa/traefik/` lalu `docker compose up -d` |

**Restore backup Nexosystems** mengembalikan **seluruh server** ke tanggal lama — bukan penyebab edit “tidak kena”, tapi bisa **menghapus** perubahan yang sudah masuk disk.

---

## 1. WordPress (lpmpjk.org & ptsbi.org)

File tema/plugin/upload ada di **volume Docker**, bukan di folder tipis `wordpress/`.

### Cari path volume di server

```bash
docker volume inspect wordpress_wordpress_lpmpjk --format '{{.Mountpoint}}'
docker volume inspect wordpress_wordpress_ptsbi --format '{{.Mountpoint}}'
```

Biasanya di bawah `/var/lib/docker/volumes/.../_data/`.

### Edit file

- **Disarankan:** WP Admin → Appearance / Plugins  
- **WinSCP (root):** buka path `Mountpoint` di atas  
- Setelah ubah file PHP: `docker compose -f /home/togaa/wordpress/docker-compose.yml restart`

### Tambah site WordPress baru (lewat Traefik)

**Panduan lengkap (sama persis ptsbi / lpmpjk):**  
`deploy/wordpress/STANDAR-WORDPRESS-TRAEFIK.md`  
Template compose: `deploy/wordpress/templates/wordpress-service.yml.example`  
Script: `deploy/wordpress/scripts/new-wp-site.sh`

Ringkas:

1. Buat database di MySQL shared (`wp_SLUG` + user `SLUG`)  
2. Salin service `wordpress-SITENAME` ke `/home/togaa/wordpress/docker-compose.yml`  
3. DNS A `@` dan `www` → `5.175.245.78`  
4. `cd /home/togaa/wordpress && docker compose up -d wordpress-SITENAME`

---

## 2. Tarombo (tarombo.ptsbi.org)

Path benar: **`/home/togaa/tarombo-app/`**

Setelah ubah `app.py`, templates, static:

```bash
cd /home/togaa/tarombo-app
docker compose up -d --build
```

Hanya `restart` **tidak** memuat kode baru.

Cek log:

```bash
docker logs tarombo-web --tail 50
```

---

## 3. Traefik (SSL & routing)

Path: **`/home/togaa/traefik/`**

- `docker-compose.yml` — router, entrypoints  
- `data/letsencrypt/acme.json` — **jangan edit manual** (permission 600, root)  
- Setelah ubah config:

```bash
cd /home/togaa/traefik
docker compose up -d
```

---

## 4. MySQL

- Data: `/home/togaa/docker/volumes/mysqldata/`  
- Config: `/home/togaa/mysql/docker-compose.yml`  
- Port hanya **127.0.0.1:3306** (tidak publik) — aman  

Jangan stop MySQL saat website ramai tanpa maintenance.

---

## 5. Perintah berguna

```bash
docker ps
cd /home/togaa/traefik && docker compose logs -f --tail 30
cd /home/togaa/wordpress && docker compose restart
cd /home/togaa/tarombo-app && docker compose up -d --build
```

---

## 6. WinSCP

| Setting | Nilai |
|---------|--------|
| User | `togaa` (setelah migrasi + finalize) |
| Path | `/home/togaa/` |
| Key | SSH key (sama seperti sebelumnya) |

Folder `traefik/data/` owned root — normal jika read-only untuk `togaa`; ubah compose di `traefik/` lalu deploy dengan `sudo docker compose`.

---

## 7. Backup

| Jenis | Kapan |
|-------|--------|
| Nexosystems harian | Otomatis; **Restore** = seluruh VM mundur |
| Manual `tar` | `tar -czf /root/backup-pre-migrate/home-togaa-DATE.tar.gz /home/togaa` |

Sebelum perubahan besar: buat `tar` atau pastikan backup harian Nexosystems sudah jalan.
