@echo off
setlocal
cd /d "%~dp0"
echo ============================================
echo  PTSBI - Deploy manual ke server vm21197
echo  (SSH key default: %%USERPROFILE%%\.ssh\id_ed25519)
echo ============================================
echo.

set HOST=togaa@5.175.245.78
set WP=wordpress-ptsbi

where py >nul 2>&1 && set PY=py -3
if not defined PY where python >nul 2>&1 && set PY=python
if not defined PY (
  echo Python tidak ditemukan.
  pause
  exit /b 1
)

echo [1/4] Build paket Tarombo...
%PY% scripts\build_ubuntu_package.py
if errorlevel 1 goto :fail

echo.
echo [2/4] Upload Tarombo...
scp deploy\tarombo-app.zip %HOST%:/home/togaa/tarombo-app.zip
if errorlevel 1 goto :fail

echo.
echo [3/4] Unzip + rebuild Docker Tarombo...
ssh %HOST% "unzip -o -q /home/togaa/tarombo-app.zip -d /home/togaa && cd /home/togaa/tarombo-app && chmod +x scripts/*.sh 2>nul || true && docker compose -f docker-compose.server.yml up -d --build && bash scripts/post-deploy-tarombo.sh && docker compose -f docker-compose.server.yml ps"
if errorlevel 1 goto :fail

echo.
echo [4/4] Deploy plugin WordPress (%WP%)...
ssh %HOST% "mkdir -p /home/togaa/deploy-staging/ptsbi-premium"
scp -r wordpress\ptsbi-premium\* %HOST%:/home/togaa/deploy-staging/ptsbi-premium/
if errorlevel 1 goto :fail
ssh %HOST% "docker exec %WP% rm -rf /var/www/html/wp-content/plugins/ptsbi-premium 2>nul & docker cp /home/togaa/deploy-staging/ptsbi-premium %WP%:/var/www/html/wp-content/plugins/ptsbi-premium & docker exec %WP% chown -R www-data:www-data /var/www/html/wp-content/plugins/ptsbi-premium"
if errorlevel 1 goto :fail

echo.
echo ===== DEPLOY SELESAI =====
echo  Tarombo  : https://tarombo.ptsbi.org
echo  WordPress: https://ptsbi.org
echo.
pause
exit /b 0

:fail
echo.
echo DEPLOY GAGAL. Cek koneksi SSH: ssh %HOST% "echo OK"
pause
exit /b 1
