# Sandbox WordPress — slug `belajar` / hosts `wp-belajar.local`

Site khusus belajar, terpisah dari lpmpjk.org dan ptsbi.org.

| Item | Nilai |
|------|--------|
| Service / container | `wordpress-belajar` |
| Volume | `wordpress_belajar` |
| Database | `wp_belajar` |
| User MySQL | `belajar` |
| Hosts (sementara) | `wp-belajar.local` |
| URL sementara | **http://**wp-belajar.local (HTTP, tanpa SSL) |

---

## Deploy di server

1. Upload ke server (WinSCP sebagai `togaa`):
   - `deploy/wordpress/docker-compose.belajar.yml` → `/home/togaa/wordpress/`
   - `deploy/wordpress/scripts/deploy-belajar-fresh.sh` → `/home/togaa/wordpress/scripts/` (buat folder `scripts` jika belum ada)

2. SSH:

```bash
chmod +x /home/togaa/wordpress/scripts/deploy-belajar-fresh.sh
export BELAJAR_DB_PASS='GantiPasswordKuat123'
bash /home/togaa/wordpress/scripts/deploy-belajar-fresh.sh
```

Atau manual:

```bash
cd /home/togaa/wordpress
export BELAJAR_DB_PASS='GantiPasswordKuat123'
docker compose -f docker-compose.yml -f docker-compose.belajar.yml up -d wordpress-belajar
```

---

## PC Windows — file hosts

Buka Notepad **sebagai Administrator**, edit:

`C:\Windows\System32\drivers\etc\hosts`

Tambahkan:

```text
5.175.245.78  wp-belajar.local www.wp-belajar.local
```

Simpan, lalu buka: **http://wp-belajar.local**

> Pakai **http://** (bukan https) — domain `.local` belum punya sertifikat Let's Encrypt.  
> **Penting:** Traefik di server ini memakai entrypoint **`http`** (bukan `web`). Label harus `entrypoints=http`.

---

## Wizard WordPress

- Judul site: mis. *Sandbox Belajar*
- Admin: user baru khusus belajar (jangan pakai password produksi)
- Settings → Reading → centang *Discourage search engines* (opsional)

---

## Nanti pakai domain asli

1. Ganti label Traefik di `docker-compose.belajar.yml`: `Host(\`domain-anda.org\`)` + entrypoint `websecure` + TLS (salin dari `wordpress-ptsbi`).
2. DNS A `@` dan `www` → `5.175.245.78`
3. Update `WP_HOME` / `WP_SITEURL` ke `https://domain-anda.org`
4. Hapus baris hosts di laptop

---

## Hapus sandbox (jika tidak dipakai lagi)

```bash
cd /home/togaa/wordpress
docker compose -f docker-compose.yml -f docker-compose.belajar.yml down wordpress-belajar
docker volume rm wordpress_wordpress_belajar
docker exec mysql mysql -uroot -p -e "DROP DATABASE wp_belajar; DROP USER 'belajar'@'%';"
```
