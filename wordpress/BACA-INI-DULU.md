# Plugin PTSBI Premium — mana yang dipakai?

## Canonical (satu plugin saja)

- **Folder:** `ptsbi-premium/`
- **Nama plugin:** "Premium Organization"
- **Versi plugin:** **3.0.0**
- **Modul anggota (internal):** **1.0.0** (`PTPRM_MEMBER_VERSION`)
- **Option DB:** `ptprm_options`
- **Menu admin:** "Premium Plugin"

Modul anggota **sudah di dalam** plugin ini. **Jangan** aktifkan folder `ptsbi-members/` (deprecated).

## Build ZIP untuk upload ke server

```
BUILD-ZIP.bat
```

Hasil: **`ptsbi-premium.zip`** — unzip ke `wp-content/plugins/ptsbi-premium/`

## Jika muncul dua plugin "Premium Organization"

1. Hapus folder lama di server: `ptsbi-premium262`, salinan duplikat, atau `ptsbi-members`
2. Pastikan hanya ada **satu** folder: `wp-content/plugins/ptsbi-premium/ptsbi-premium.php`
3. Nonaktifkan & hapus plugin **PTSBI Members (deprecated)** jika masih terpasang
