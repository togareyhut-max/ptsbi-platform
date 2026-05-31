@echo off
setlocal
set ROOT=%~dp0
set SRC=%ROOT%ptsbi-members
set OUT=%ROOT%ptsbi-members.zip

if not exist "%SRC%\ptsbi-members.php" (
  echo Folder plugin tidak ditemukan: %SRC%
  pause
  exit /b 1
)
if exist "%OUT%" del /f "%OUT%"

powershell -NoProfile -ExecutionPolicy Bypass -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::CreateFromDirectory('%SRC%', '%OUT%', [System.IO.Compression.CompressionLevel]::Optimal, $true)"

if exist "%OUT%" (
  echo.
  echo ===== ZIP siap =====
  echo %OUT%
) else (
  echo GAGAL membuat ZIP.
)
echo.
pause
