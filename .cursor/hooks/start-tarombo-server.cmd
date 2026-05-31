@echo off
setlocal
set ROOT=%~dp0..\..
cd /d "%ROOT%"

if not exist "%ROOT%\.venv\Scripts\python.exe" (
  py -m venv "%ROOT%\.venv" 2>nul
)

set USE_SQLITE=1
set DATABASE_URL=
set FLASK_DEBUG=1

"%ROOT%\.venv\Scripts\python.exe" -m pip install -q -r "%ROOT%\requirements-sqlite.txt" 2>nul
"%ROOT%\.venv\Scripts\python.exe" "%ROOT%\scripts\setup_local_db.py" > "%ROOT%\server_boot.log" 2>&1

for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":5000" ^| findstr "LISTENING"') do (
  taskkill /F /PID %%a >nul 2>&1
)

start "" /B "%ROOT%\.venv\Scripts\python.exe" "%ROOT%\app.py" >> "%ROOT%\server_boot.log" 2>&1
exit /b 0
