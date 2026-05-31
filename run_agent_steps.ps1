# One-shot: steps 1-8 from agent task. Run in PowerShell:
#   Set-Location C:\Users\togar\.cursor\projects\empty-window
#   powershell -NoProfile -ExecutionPolicy Bypass -File .\run_agent_steps.ps1
$ErrorActionPreference = "Continue"
$root = "C:\Users\togar\.cursor\projects\empty-window"
$log = Join-Path $root "agent_full_run.log"
Set-Location $root
"" | Set-Content $log -Encoding utf8

function W([string]$s) { Add-Content $log $s -Encoding utf8; Write-Host $s }

W "=== STEP 2: pip install ==="
& "$root\.venv\Scripts\python.exe" -m pip install -r requirements-sqlite.txt 2>&1 | ForEach-Object { W $_ }
W "EXIT_PIP=$LASTEXITCODE"

$env:USE_SQLITE = "1"
Remove-Item Env:DATABASE_URL -ErrorAction SilentlyContinue

W ""
W "=== STEP 4: setup_local_db.py ==="
& "$root\.venv\Scripts\python.exe" scripts\setup_local_db.py 2>&1 | ForEach-Object { W $_ }
$setupExit = $LASTEXITCODE
W "EXIT_SETUP=$setupExit"

W ""
W "=== STEP 5: kill port 5000 ==="
Get-NetTCPConnection -LocalPort 5000 -State Listen -ErrorAction SilentlyContinue |
  ForEach-Object { W "Killing PID $($_.OwningProcess)"; Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }

W ""
W "=== STEP 6-7: start app, wait 5s ==="
$env:USE_SQLITE = "1"
$appJob = Start-Process -FilePath "$root\.venv\Scripts\python.exe" -ArgumentList "app.py" -WorkingDirectory $root -PassThru -WindowStyle Hidden
Start-Sleep -Seconds 5
W "App PID=$($appJob.Id)"

W ""
W "=== STEP 8: curl /hasil ==="
try {
  $r = Invoke-WebRequest -Uri "http://127.0.0.1:5000/hasil" -UseBasicParsing -TimeoutSec 10
  W $r.Content.Substring(0, [Math]::Min(2000, $r.Content.Length))
  W "HTTP_CODE:$($r.StatusCode)"
} catch {
  W $_.Exception.Message
  if ($_.Exception.Response) { W "HTTP_CODE:$([int]$_.Exception.Response.StatusCode)" }
}

if ($setupExit -ne 0 -or -not $?) {
  W ""
  W "=== diag_hasil.py ==="
  & "$root\.venv\Scripts\python.exe" scripts\diag_hasil.py 2>&1 | ForEach-Object { W $_ }
  if (Test-Path "$root\diag_hasil_out.txt") {
    W "--- diag_hasil_out.txt ---"
    Get-Content "$root\diag_hasil_out.txt" -Raw | ForEach-Object { W $_ }
  }
}

W ""
W "DONE. Log: $log"
