@echo off
cd /d "%~dp0"
echo ============================================
echo  Tarombo PTSBI - Paket deploy Ubuntu 24
echo ============================================
echo.

set PY=
if exist ".venv\Scripts\python.exe" set PY=.venv\Scripts\python.exe
if not defined PY where py >nul 2>&1 && set PY=py -3
if not defined PY (
  echo Python tidak ditemukan. Pasang Python 3.11+ atau jalankan SETUP_LOKAL.bat
  pause
  exit /b 1
)

%PY% scripts\build_ubuntu_package.py
if errorlevel 1 (
  echo GAGAL membangun paket.
  pause
  exit /b 1
)

echo.
echo Upload ke server salah satu:
echo   Folder: %~dp0deploy\tarombo-app
echo   Zip   : %~dp0deploy\tarombo-app.zip
echo.
echo Baca MULAI-DISINI.txt di dalam folder paket.
pause
