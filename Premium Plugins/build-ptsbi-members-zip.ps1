# Zip plugin ptsbi-members untuk upload ke server WordPress
$ErrorActionPreference = 'Stop'
$src = Join-Path $PSScriptRoot 'ptsbi-members'
$out = Join-Path $PSScriptRoot 'ptsbi-members.zip'
if (-not (Test-Path $src)) { throw "Folder tidak ada: $src" }
if (Test-Path $out) { Remove-Item $out -Force }
Compress-Archive -Path $src -DestinationPath $out -CompressionLevel Optimal
Write-Host "OK: $out"
