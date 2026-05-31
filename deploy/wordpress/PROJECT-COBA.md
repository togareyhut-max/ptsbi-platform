# project-coba — WordPress baru

| Item | Nilai |
|------|--------|
| Container | `wordpress-project-coba` |
| Database | `wp_project_coba` |
| User MySQL | `project_coba` |
| Password default | `PasswordProjectCobaWordpress` |
| URL (hosts) | http://project-coba.local |

**Tidak disentuh:** ptsbi, lpmpjk, kotakita, belajar, tarombo

---

## Deploy

Upload `scripts/deploy-project-coba.sh` → `/home/togaa/`

```bash
chmod +x /home/togaa/deploy-project-coba.sh
bash /home/togaa/deploy-project-coba.sh
```

Password custom:

```bash
export PROJECT_COBA_DB_PASS='PasswordProjectCobaWordpress'
bash /home/togaa/deploy-project-coba.sh
```

## PC — hosts

```text
5.175.245.78  project-coba.local www.project-coba.local
```

Browser: **http://project-coba.local**

## Reset fresh

```bash
RESET=1 bash /home/togaa/deploy-project-coba.sh
```

`RESET=1` sekarang **benar-benar bersih**:
- Stop + `compose rm -v` container
- Hapus **semua** volume yang namanya mengandung `project_coba` / `project-coba`
- Jika volume tidak bisa dihapus: **kosongkan isinya** (alpine) lalu hapus lagi
- Drop database + user MySQL
- Setelah container naik: hapus `wp-content/themes/*`, `plugins/*`, `uploads/*` agar tema bisa di-install ulang

Jika masih ada folder tema setelah reset, jalankan manual:

```bash
VOL=$(docker volume ls -q | grep -i project_coba | head -1)
docker run --rm -v "${VOL}:/var/www/html" alpine:3.20 sh -c 'rm -rf /var/www/html/*'
RESET=1 bash /home/togaa/deploy-project-coba.sh
```

## Domain asli nanti

Ganti `project-coba.local` di `docker-compose.project-coba.yml` → label Traefik + DNS A → `5.175.245.78` (sama kotakita).
