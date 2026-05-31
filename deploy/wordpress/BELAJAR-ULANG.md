# wp-belajar — hapus total & buat ulang (1 perintah)

**Tidak menyentuh:** ptsbi.org, lpmpjk.org, kotakita.net, tarombo.ptsbi.org, traefik, mysql

---

## Di server (konsol root)

1. Upload **`scripts/redeploy-belajar.sh`** → `/home/togaa/redeploy-belajar.sh`

2. Jalankan:

```bash
chmod +x /home/togaa/redeploy-belajar.sh
bash /home/togaa/redeploy-belajar.sh
```

Opsional ganti password DB:

```bash
export BELAJAR_DB_PASS='PasswordAnda'
bash /home/togaa/redeploy-belajar.sh
```

Skrip **gagal (exit 1)** jika Traefik masih 404 — site lain tidak diubah.

---

## Di PC

File hosts (Administrator):

```text
5.175.245.78  wp-belajar.local www.wp-belajar.local
```

Browser: **http://wp-belajar.local** → wizard WordPress.

---

## Perbedaan dengan kesalahan sebelumnya

| Sebelum | Sekarang |
|---------|----------|
| `entrypoints=web` (salah) | `entrypoints=http` (sama Traefik server) |
| Baca dari template umum | Baca dari ptsbi + `/home/togaa/traefik/` |
| Deploy tanpa cek | **Gagal** jika curl masih 404 |

Sama pola routing seperti kotakita kemarin — hanya domain `wp-belajar.local` + HTTP saja (tanpa redirect HTTPS).
