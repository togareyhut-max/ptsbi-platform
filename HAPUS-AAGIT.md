# Menghapus `aagit` sepenuhnya

Gunakan **hanya** jika:

- [ ] `ssh togaa@5.175.245.78` berhasil  
- [ ] `docker ps` dari user togaa (dengan sudo) OK  
- [ ] lpmpjk.org & ptsbi.org jalan  
- [ ] Stack aktif di `/home/togaa/` (bukan `/home/aagit/`)

---

## Langkah

**1. Upload** `remove-aagit.sh` ke `/root/` di server.

**2. Jalankan sebagai root:**

```bash
chmod +x /root/remove-aagit.sh
/root/remove-aagit.sh
```

Ketik **YES** saat diminta.

**3. Skrip akan:**

- Cek tidak ada path/container yang masih pakai `/home/aagit`
- Stop compose lama di `/home/aagit` (jika ada)
- Arsip home ke `/root/archive-removed-users/aagit-home-YYYYMMDD.tar.gz`
- `userdel -r aagit` + hapus `/home/aagit`

---

## Hapus `adminuser` juga (opsional)

Setelah `aagit` hilang, jika hanya `togaa` yang dipakai:

```bash
id adminuser && userdel -r adminuser
# atau arsip dulu:
tar -czf /root/archive-removed-users/adminuser-home.tar.gz /home/adminuser 2>/dev/null
userdel -r adminuser
```

---

## Kunci SSH hanya `togaa`

```bash
./migrate-to-togaa.sh finalize
```

Atau manual: `PermitRootLogin no`, `AllowUsers togaa`, `PasswordAuthentication no`.

---

## Rollback

Jika salah hapus, restore dari:

- `/root/archive-removed-users/aagit-home-*.tar.gz`
- Backup Nexosystems (seluruh VM mundur)
