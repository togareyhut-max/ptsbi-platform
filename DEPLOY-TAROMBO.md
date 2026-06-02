# Deploy Tarombo — singkat

Domain: **https://tarombo.ptsbi.org**  
Path server: **/home/togaa/tarombo-app**

---

## 1. DNS (panel domain ptsbi.org)

| Host | Type | Value |
|------|------|--------|
| `tarombo` | A | `5.175.245.78` |

Tunggu ~30 menit.

---

## 2. Upload (WinSCP root)

- Zip folder `deploy/tarombo-app` di PC → upload & extract ke `/home/togaa/tarombo-app/`
- Atau rename folder lama: `tarombo-app` → `tarombo-app.bak`

Harus ada: `docker-compose.server.yml`, `Dockerfile`, `app.py`, `.env.example`

---

## 3. Konsol

```bash
cd /home/togaa/tarombo-app
cp .env.example .env
nano .env
```

Ganti: `POSTGRES_PASSWORD` dan `SECRET_KEY` (acak, panjang).

```bash
chmod +x deploy-tarombo-traefik.sh
./deploy-tarombo-traefik.sh
```

Atau manual:

```bash
docker compose -f docker-compose.server.yml up -d --build
docker ps | grep tarombo
```

---

## 4. Tes

Browser: **https://tarombo.ptsbi.org**

Login seed (password `12345678`): `admin@ptsbi.org`, `pengurus@ptsbi.org`, `anggota@ptsbi.org`, `developer@ptsbi.org` → **ganti password**.

---

## Masalah?

```bash
docker logs tarombo-web --tail 30
docker logs traefik 2>&1 | tail -15
```

**DB baru (PostgreSQL)** — data silsilah lama tidak otomatis pindah.

---

## Restart/repair aman (SSH manual)

Jalankan dari PC (Git Bash/WSL):

```bash
SSH_HOST=5.175.245.78 SSH_USER=togaa bash scripts/ops/restart-tarombo-over-ssh.sh
```

Ini akan menjalankan `docker compose up -d --build` dan self-heal (`scripts/post-deploy-tarombo.sh`) di server, lalu ping `/v1/ping`.
