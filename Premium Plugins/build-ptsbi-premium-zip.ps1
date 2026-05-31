# Zip plugin ptsbi-premium untuk upload ke server WordPress
#
# Jika error "running scripts is disabled", jalankan dari CMD/PowerShell:
#   powershell -ExecutionPolicy Bypass -File ".\build-ptsbi-premium-zip.ps1"
#
# Atau satu baris (tanpa file .ps1):
#   powershell -ExecutionPolicy Bypass -Command "Compress-Archive -Path '.\ptsbi-premium' -DestinationPath '.\ptsbi-premium.zip' -Force"
#
$ErrorActionPreference = 'Stop'
$src = Join-Path $PSScriptRoot 'ptsbi-premium'
$out = Join-Path $PSScriptRoot 'ptsbi-premium.zip'
if (-not (Test-Path $src)) { throw "Folder tidak ada: $src" }
if (Test-Path $out) { Remove-Item $out -Force }
Compress-Archive -Path $src -DestinationPath $out -CompressionLevel Optimal
Write-Host "OK: $out"
Write-Host "Upload ZIP ke server, lalu unzip di /home/togaa/ptsbi-premium"
