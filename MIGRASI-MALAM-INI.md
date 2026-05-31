# Checklist migrasi malam ini (vm21197)

**Estimasi downtime:** 10–20 menit  
**Rollback:** `./migrate-to-togaa.sh rollback`

---

## Sebelum mulai (30 menit sebelumnya)

- [ ] Beri tahu pemakai site: maintenance malam ini  
- [ ] Backup sudah ada: `/root/backup-pre-migrate/home-aagit-20260522.tar.gz`  
- [ ] **Jangan** Restore backup Nexosystems  
- [ ] Siapkan SSH ke server sebagai **root**  
- [ ] Buka terminal cadangan (jangan tutup root sampai tes `togaa` OK)  
- [ ] Upload `migrate-to-togaa.sh` ke `/root/` (WinSCP)

```bash
chmod +x /root/migrate-to-togaa.sh
```

---

## Jalankan migrasi

```bash
cd /root
./migrate-to-togaa.sh migrate
```

Skrip akan:

1. Buat user **togaa** (+ salin SSH key dari aagit)  
2. Stop Traefik → WordPress → Tarombo → MySQL  
3. Copy `/home/aagit` → `/home/togaa`  
4. Ganti path di config  
5. Start MySQL → WordPress → Tarombo (build) → Traefik  
6. Cek HTTPS ketiga domain  

---

## Tes manual (wajib)

| URL | OK? |
|-----|-----|
| https://lpmpjk.org | [ ] |
| https://ptsbi.org | [ ] |
| https://tarombo.ptsbi.org | [ ] |

Terminal baru:

```bash
ssh togaa@IP_SERVER
docker ps
```

WinSCP: login **togaa**, path `/home/togaa/`.

---

## Jika gagal

```bash
./migrate-to-togaa.sh rollback
```

Atau Restore backup Nexosystems (seluruh server mundur — hindari kecuali darurat).

---

## Besok / 24–48 jam (jika semua OK)

```bash
./migrate-to-togaa.sh finalize
```

Ini akan:

- Lock user **aagit** dan **adminuser**  
- Rename `/home/aagit` → disabled  
- SSH: root login off, hanya **togaa**, password off  

**Sebelum finalize:** pastikan `ssh togaa@server` berhasil.

---

## Setelah migrasi

Baca **PANDUAN-EDIT-SITE.md** — edit WP lewat volume Docker; Tarombo perlu `--build`.
