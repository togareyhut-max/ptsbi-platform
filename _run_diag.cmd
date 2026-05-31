@echo off
cd /d "%~dp0"
set USE_SQLITE=1
set DATABASE_URL=
set FLASK_DEBUG=1
"%~dp0.venv\Scripts\python.exe" "%~dp0scripts\diag_hasil.py"
type "%~dp0diag_hasil_out.txt" > "%~dp0diag_hasil_log.txt" 2>&1
