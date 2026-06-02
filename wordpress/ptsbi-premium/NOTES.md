# Notes index — `ptsbi-premium`

File ini adalah **catatan ringkas yang gampang dipanggil kembali** (Ctrl+P → ketik `NOTES`).

## Quick links
- **Panduan deploy**: `DEPLOYING.md`
- **Artefak & laporan deploy lokal (auto-generated)**: `releases-local/` (di-ignore git)

## Prompt / prinsip kerja (ringkas, bisa dipakai ulang)
- **Single source of truth plugin**: hanya `wordpress/ptsbi-premium/` (folder `Premium Plugins/ptsbi-premium/` dianggap noise dan di-ignore).
- **Tarombo = master data anggota**: semua data pendaftar/anggota (status, profil, direktori) wajib tersimpan & terbaca dari DB Tarombo (PostgreSQL) via API.
- **WordPress hanya untuk fitur WP**:
  - konten: berita/kegiatan (post), halaman, template
  - media: foto pengurus tersimpan di Media Library WP, dipakai via URL
  - akun WP dipakai untuk login/session + role akses UI
- **API wajib sukses** saat aksi simpan (registrasi, update profil, approve). Kalau API/DB down:
  - aksi simpan harus gagal (tidak ada data “nyangkut” di DB WP)
  - UI menampilkan pesan jelas: “data tidak bisa dipanggil/ditulis karena API/DB”
- **Deploy wajib memastikan Tarombo hidup** (self-heal + watchdog).

## Baseline
- **Git tag baseline**: `baseline-2026-06-02`
- **ZIP baseline lokal**: cek file `releases-local/ptsbi-premium_*_baseline_*.zip`
- **Deploy notes baseline**: cek `releases-local/deploy-notes_baseline_*.md`

## Report implementasi (yang sudah dilakukan)
- **Local ZIP + deploy notes**:
  - Script: `wordpress/ptsbi-premium/scripts/predeploy.ps1`
  - Output: `wordpress/ptsbi-premium/releases-local/` (di-ignore git)
- **Baseline rollback**: tag `baseline-2026-06-02` + arsip ZIP baseline di `releases-local/`
- **Self-healing deploy**:
  - Server post-deploy: `scripts/deploy/post-deploy-tarombo.sh` (restart + ping `/v1/ping` + cek `/v1/health/db`)
  - Deploy manual PC: `DEPLOY-NOW.bat` memanggil post-deploy server
  - Deploy plugin GitHub/SSH: `scripts/deploy/sync-wordpress-plugin.sh` memastikan Tarombo hidup dulu
- **Ops aman (manual SSH)**:
  - `scripts/ops/restart-tarombo-over-ssh.sh` (restart + verify ping + db health)
- **Auto-heal server-side (opsional)**:
  - `deploy/tarombo-app/scripts/watchdog-tarombo.sh`
  - systemd: `deploy/tarombo-app/deploy/systemd/tarombo-watchdog.{service,timer}`
- **Health endpoint Tarombo**:
  - `GET /v1/ping`
  - `GET /v1/health/db` (membedakan API up vs DB down)

## Cara deploy (manual upload ke WordPress)
Jalankan:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ".\wordpress\ptsbi-premium\scripts\predeploy.ps1" -Mode release
```

Ambil output:
- `wordpress/ptsbi-premium/releases-local/*.zip`
- `wordpress/ptsbi-premium/releases-local/deploy-notes_release_*.md`

## Cara cepat “ambil laporan terakhir”
Cari file terbaru di `releases-local/`:
- `deploy-notes_release_*.md` (yang timestamp paling baru)

