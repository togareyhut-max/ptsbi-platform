@echo off
cd /d "%~dp0"
echo === Tarombo PTSBI - Setup Lokal (SQLite) ===
echo.

where py >nul 2>nul
if %errorlevel%==0 (set PY=py) else (set PY=python)

if not exist ".venv" (
  echo [1/4] Membuat virtual environment...
  %PY% -m venv .venv
)

set VENV_PY=%~dp0.venv\Scripts\python.exe
if not exist "%VENV_PY%" (
  echo ERROR: Python venv tidak ditemukan di .venv\Scripts\python.exe
  pause
  exit /b 1
)

echo [2/4] Install dependensi...
"%VENV_PY%" -m pip install --upgrade pip
"%VENV_PY%" -m pip install -r requirements-sqlite.txt
if errorlevel 1 (
  echo ERROR: pip install gagal.
  pause
  exit /b 1
)

if not exist ".env" copy /Y .env.example .env >nul

echo [3/4] Setup database SQLite...
set USE_SQLITE=1
set DATABASE_URL=
"%VENV_PY%" scripts\setup_local_db.py
if errorlevel 1 (
  echo.
  echo Setup database GAGAL. Lihat error di atas.
  pause
  exit /b 1
)

echo.
echo [4/4] Setup BERHASIL.
echo.
echo Jalankan server dengan: START_APP.bat
echo Atau: "%VENV_PY%" app.py
echo.
echo Buka: http://127.0.0.1:5000/login kemudian /tarombo
echo Akun bawaan (password 12345678):
echo   admin@ptsbi.org  pengurus@ptsbi.org  anggota@ptsbi.org  developer@ptsbi.org
echo.
pause
