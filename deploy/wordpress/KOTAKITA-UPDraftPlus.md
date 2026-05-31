# Kotakita.net — WordPress baru (UpdraftPlus)

## Yang Anda pikir vs yang sebenarnya

| Anda pikir | Sebenarnya di server Anda |
|------------|---------------------------|
| Install WordPress di Ubuntu (`apt install`) | WordPress jalan di **Docker** (sama ptsbi & lpmpjk) |
| Password lama kotakita | **Tidak dipakai.** WP baru = database kosong + admin baru |
| Banyak perintah SSH | **2 skrip** saja: bersihkan → deploy fresh |

**MYSQL_ROOT** = password admin **MySQL server** (sudah ada untuk ptsbi/lpmpjk). Skrip baca otomatis dari `/home/togaa/mysql/docker-compose.yml`. **Bukan** password situs kotakita lama.

**KOTAKITA_DB_PASS** = password **baru** untuk database site kotakita (boleh `PasswordKotakitaKuat` atau Anda ganti).

---

## SSH — cuma ini

```bash
ssh vm21197
cd ~/kotakita
bash cleanup-kotakita.sh
bash deploy-kotakita-fresh.sh
```

Selesai. WordPress **kosong** siap instalasi wizard + UpdraftPlus.

---

## Setelah itu (browser, bukan SSH)

1. Hosts laptop: `5.175.245.78 kotakita.net www.kotakita.net`
2. https://kotakita.net → **Instal WordPress** (bahasa, user admin **baru**)
3. Plugin → UpdraftPlus → **Restore** backup dari kotakita.net lama
4. Teman: DNS A → `5.175.245.78`

---

## Alur singkat

```
Situs lama (teman)  →  UpdraftPlus backup  →  download zip
Server Anda         →  WP Docker kosong     →  Restore zip
Teman               →  DNS ke IP Anda      →  live
```
