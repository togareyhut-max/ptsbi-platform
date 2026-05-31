@echo off
setlocal
set ROOT=%~dp0
set SRC=%ROOT%ptsbi-premium
set OUT=%ROOT%ptsbi-premium.zip

if not exist "%SRC%\ptsbi-premium.php" (
  echo Folder plugin tidak ditemukan: %SRC%
  pause
  exit /b 1
)
if exist "%OUT%" del /f "%OUT%"

REM Pakai .NET ZipFile.CreateFromDirectory supaya subfolder includes/ dan assets/ pasti ikut.
powershell -NoProfile -ExecutionPolicy Bypass -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::CreateFromDirectory('%SRC%', '%OUT%', [System.IO.Compression.CompressionLevel]::Optimal, $true)"

if exist "%OUT%" (
  echo.
  echo ===== ZIP siap =====
  echo %OUT%
  echo.
  echo Isi ZIP:
  powershell -NoProfile -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::OpenRead('%OUT%').Entries | ForEach-Object { '  ' + $_.FullName } | Sort-Object"
) else (
  echo GAGAL membuat ZIP. Pastikan PowerShell tersedia.
)
echo.
pause
