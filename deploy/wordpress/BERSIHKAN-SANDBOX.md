# Bersihkan deploy gagal — **belajar saja**

| Status | Site |
|--------|------|
| **Jangan disentuh** | ptsbi.org, lpmpjk.org, tarombo.ptsbi.org, **kotakita.net** (sudah OK) |
| **Dihapus** | `wordpress-belajar`, DB `wp_belajar`, file `docker-compose.belajar.yml` |

Kotakita hanya menunggu **DNS A record** dari teman Anda — bukan dihapus.

---

## Skrip

Upload `scripts/cleanup-sandbox-failed.sh` → `/home/togaa/`

```bash
chmod +x /home/togaa/cleanup-sandbox-failed.sh
DRY_RUN=1 bash /home/togaa/cleanup-sandbox-failed.sh
bash /home/togaa/cleanup-sandbox-failed.sh
```

---

## DNS kotakita (untuk teman)

Di panel domain **kotakita.net**:

| Tipe | Host | Nilai |
|------|------|--------|
| A | `@` | `5.175.245.78` |
| A | `www` | `5.175.245.78` |

Tunggu propagasi (menit–24 jam), lalu buka **https://kotakita.net**

Tes dari laptop sebelum DNS global (opsional) — file hosts:

```text
5.175.245.78  kotakita.net www.kotakita.net
```

---

## Setelah bersih — deploy belajar ulang

1. Upload `docker-compose.belajar.yml` + `deploy-belajar-fresh.sh`
2. `bash deploy-belajar-fresh.sh`
3. Hosts PC: `5.175.245.78  wp-belajar.local www.wp-belajar.local`
4. Browser: **http://wp-belajar.local**

---

## Verifikasi

```bash
docker ps
```

Harus ada: `wordpress-kotakita`  
Tidak ada: `wordpress-belajar`
