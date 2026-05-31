# Setup lokal tanpa perlu "activate" (hindari error ExecutionPolicy)
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

$venvPy = Join-Path $PSScriptRoot ".venv\Scripts\python.exe"

if (-not (Test-Path $venvPy)) {
    Write-Host "Membuat .venv ..."
    if (Get-Command py -ErrorAction SilentlyContinue) { py -m venv .venv }
    else { python -m venv .venv }
}

Write-Host "Install dependensi ..."
& $venvPy -m pip install --upgrade pip
& $venvPy -m pip install -r requirements-sqlite.txt

if (-not (Test-Path ".env")) { Copy-Item .env.example .env }

$env:USE_SQLITE = "1"
Remove-Item Env:DATABASE_URL -ErrorAction SilentlyContinue

Write-Host "Setup database ..."
& $venvPy scripts\setup_local_db.py

Write-Host ""
Write-Host "Selesai. Jalankan server:"
Write-Host "  .\START_APP.bat"
Write-Host "  atau: & '$venvPy' app.py"
