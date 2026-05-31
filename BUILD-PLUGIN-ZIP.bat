@echo off
setlocal
set SRC=%~dp0ptsbi-premium-enhancer
set OUT=%~dp0ptsbi-premium-enhancer.zip
if not exist "%SRC%\ptsbi-premium-enhancer.php" (
  echo Folder plugin tidak ditemukan: %SRC%
  exit /b 1
)
if exist "%OUT%" del /f "%OUT%"
powershell -NoProfile -Command "Compress-Archive -Path '%SRC%\*' -DestinationPath '%OUT%' -Force"
echo.
echo ZIP siap: %OUT%
pause
