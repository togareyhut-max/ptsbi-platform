# SSH togaa + deploy Tarombo (tarombo.ptsbi.org)

**Situasi:** lpmpjk.org & ptsbi.org sudah jalan. tarombo.ptsbi.org belum.

**Rekomendasi urutan:**

1. Pastikan **SSH `togaa`** (10 menit)  
2. **Deploy tarombo-app baru** sekarang (30–45 menit) — cocok karena subdomain memang belum jalan  

---

## Bagian A — SSH `togaa` dulu

### Tes dari PC (PowerShell)

```powershell
ssh togaa@IP_SERVER
```

Jika gagal, masih sebagai **root** di server:

```bash
id togaa
ls -la /home/togaa/.ssh/
# Jika authorized_keys kosong, salin dari aagit:
mkdir -p /home/togaa/.ssh
cp /home/aagit/.ssh/authorized_keys /home/togaa/.ssh/
chown -R togaa:togaa /home/togaa/.ssh
chmod 700 /home/togaa/.ssh
chmod 600 /home/togaa/.ssh/authorized_keys
usermod -aG sudo,docker togaa
```

Tes lagi `ssh togaa@IP`. Kalau OK:

```bash
docker ps
sudo docker ps   # harus bisa
```

**Jangan** `./migrate-to-togaa.sh finalize` sebelum `ssh togaa` berhasil.

WinSCP: user **togaa**, private key, path `/home/togaa/`.

---

## Bagian B — Kenapa tarombo.ptsbi.org belum jalan?

Cek cepat (root atau togaa + sudo):

```bash
docker ps -a | grep -E 'tarombo|traefik'
docker logs tarombo-web --tail 40
curl -sI https://tarombo.ptsbi.org | head -5
```

| Gejala | Penyebab umum |
|--------|----------------|
| 404 / no route | Container tidak di network `traefik` atau label `Host` salah |
| 502 | `tarombo-web` crash / tidak listen 5000 |
| SSL error | Cert belum terbit — cek `docker logs traefik` |
| Connection refused | Traefik down atau DNS subdomain salah |

Versi **lama** di server mungkin tanpa label Traefik lengkap. Versi **baru** (`deploy/tarombo-app`) sudah disiapkan dengan `docker-compose.server.yml`.

---

## Bagian C — Naikkan tarombo-app baru (sekarang)

### Penting: database

| | Versi baru |
|---|------------|
| DB | **PostgreSQL** (container `tarombo-postgres`) |
| Data lama | Di MySQL/shared lama — **tidak otomatis pindah** |
| Awal | Akun seed (password `12345678`): `admin@`, `pengurus@`, `anggota@`, `developer@ptsbi.org` |

Jika perlu data silsilah lama, backup dulu sebelum deploy; migrasi data terpisah (bisa nanti).

### Langkah

**1. Di PC** — zip folder deploy:

```
deploy/tarombo-app/   → upload ke server
```

**2. WinSCP (togaa atau root)**  
- Backup folder lama: rename `/home/togaa/tarombo-app` → `tarombo-app.bak-DATE`  
- Upload isi `deploy/tarombo-app` ke `/home/togaa/tarombo-app/`  
- Pastikan ada: `docker-compose.server.yml`, `deploy-tarombo-traefik.sh`, `Dockerfile`, `app.py`, ...

**3. Di server:**

```bash
cd /home/togaa/tarombo-app
cp .env.example .env
nano .env
# Wajib ganti: POSTGRES_PASSWORD, SECRET_KEY (panjang & acak)

chmod +x deploy-tarombo-traefik.sh
./deploy-tarombo-traefik.sh
```

**4. Tes browser:** https://tarombo.ptsbi.org  

**5. Ganti password admin** setelah login.

### Jika certresolver Traefik bukan `letsencrypt`

```bash
grep -r certificatesResolvers /home/togaa/traefik/
```

Edit baris `certresolver=letsencrypt` di `docker-compose.server.yml` agar sama dengan nama di `traefik.yml`, lalu:

```bash
docker compose -f docker-compose.server.yml up -d --build
```

---

## Bagian D — Nanti saja?

Boleh. lpmpjk & ptsbi tidak terpengaruh. Tarombo tetap mati sampai deploy.

Risiko menunda: tidak ada; hanya subdomain tarombo yang offline.

---

## Ringkas

| Prioritas | Tindakan |
|-----------|----------|
| 1 | `ssh togaa` + WinSCP OK |
| 2 | Upload `deploy/tarombo-app` + `.env` |
| 3 | `./deploy-tarombo-traefik.sh` |
| 4 | Tes https://tarombo.ptsbi.org |
| 5 | Setelah semua OK 1–2 hari → `migrate-to-togaa.sh finalize` |

Kirim output jika gagal:

```bash
docker logs tarombo-web --tail 50
docker logs traefik --tail 30
```
