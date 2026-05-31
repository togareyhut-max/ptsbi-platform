# WordPress — PTSBI Premium Plugin

Plugin resmi untuk **ptsbi.org** (Premium Organization + modul anggota).

## Folder produksi

```
wordpress/ptsbi-premium/   ← upload ke wp-content/plugins/ptsbi-premium/
```

## Build ZIP untuk server

Dari folder `Premium Plugins/` (sementara) atau jalankan:

```bat
Premium Plugins\BUILD-ZIP.bat
```

Hasil: `ptsbi-premium.zip` — jangan di-commit ke Git (sudah di `.gitignore`).

## Jangan aktifkan

- `ptsbi-members` (deprecated, sudah digabung ke premium)
- Salinan folder `ptsbi-premium262`, dll. di server
