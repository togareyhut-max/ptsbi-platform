@echo off
cd /d "%~dp0"
echo === Tarombo PTSBI ===

set VENV_PY=%~dp0.venv\Scripts\python.exe

if not exist "%VENV_PY%" (
  echo Virtual environment belum ada. Jalankan SETUP_LOKAL.bat dulu.
  pause
  exit /b 1
)

set USE_SQLITE=1
set DATABASE_URL=
set PORT=5000
set FLASK_DEBUG=0

if not exist "%~dp0tarombo.db" (
  echo Database belum ada — menjalankan setup singkat...
  "%VENV_PY%" scripts\setup_local_db.py
  if errorlevel 1 (
    echo Setup gagal. Jalankan SETUP_LOKAL.bat atau JALANKAN.bat
    pause
    exit /b 1
  )
  echo.
)

echo Server: http://127.0.0.1:5000
echo Tekan Ctrl+C untuk berhenti.
echo.
"%VENV_PY%" app.py
pause
