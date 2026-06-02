param(
    [Parameter(Mandatory = $false)]
    [ValidateSet('baseline', 'release')]
    [string]$Mode = 'release'
)

$ErrorActionPreference = 'Stop'

function Get-PluginVersion {
    param([Parameter(Mandatory = $true)][string]$PluginMainFile)

    $content = Get-Content -LiteralPath $PluginMainFile -Raw
    $m = [regex]::Match($content, '(?m)^\s*\*\s*Version:\s*([^\r\n]+)')
    if ($m.Success) { return $m.Groups[1].Value.Trim() }

    $m2 = [regex]::Match($content, "(?m)^define\\(\\s*'PTPRM_VERSION'\\s*,\\s*'([^']+)'\\s*\\)\\s*;")
    if ($m2.Success) { return $m2.Groups[1].Value.Trim() }

    return '0.0.0'
}

function Invoke-Git {
    param(
        [Parameter(Mandatory = $true)][string[]]$Args
    )
    $out = & git @Args 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git $($Args -join ' ') failed: $out"
    }
    return $out
}

function Get-LatestTag {
    param(
        [Parameter(Mandatory = $true)][string]$Pattern
    )

    $tags = Invoke-Git -Args @('tag', '--list', $Pattern, '--sort=-creatordate')
    $first = ($tags -split "`r?`n" | Where-Object { $_.Trim() -ne '' } | Select-Object -First 1)
    return $first
}

function Split-Lines {
    param([Parameter(Mandatory = $false)][object]$Text)
    if ($null -eq $Text) { return @() }
    $s = if ($Text -is [string]) { $Text } else { ($Text | Out-String) }
    if ([string]::IsNullOrEmpty($s)) { return @() }
    return ($s -split "`n" | ForEach-Object { $_.TrimEnd("`r") } | Where-Object { $_ -ne '' })
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$PluginRoot = Resolve-Path (Join-Path $ScriptDir '..') | Select-Object -ExpandProperty Path
$PluginSlug = Split-Path -Leaf $PluginRoot
$PluginMain = Join-Path $PluginRoot 'ptsbi-premium.php'
if (-not (Test-Path -LiteralPath $PluginMain)) {
    throw "Plugin main file not found: $PluginMain"
}

$Version = Get-PluginVersion -PluginMainFile $PluginMain
$Timestamp = Get-Date -Format 'yyyy-MM-dd_HHmm'

$OutputDir = Join-Path $PluginRoot 'releases-local'
New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

# Git context
$gitRoot = (Invoke-Git -Args @('rev-parse', '--show-toplevel')).Trim()
Push-Location $gitRoot
try {
    $head = (Invoke-Git -Args @('rev-parse', 'HEAD')).Trim()
    $status = Invoke-Git -Args @('status', '--porcelain=v1')

    $baselineTag = Get-LatestTag -Pattern 'baseline-*'
    $releaseTag  = Get-LatestTag -Pattern 'release-*'

    $fromRef = $null
    if ($Mode -eq 'release') {
        if ($releaseTag) { $fromRef = $releaseTag }
        elseif ($baselineTag) { $fromRef = $baselineTag }
    } else {
        if ($baselineTag) { $fromRef = $baselineTag }
    }

    if (-not $fromRef) {
        $fromRef = (Invoke-Git -Args @('rev-list', '--max-parents=0', 'HEAD') | Select-Object -First 1).Trim()
    }

    $range = "$fromRef..HEAD"
    $log = Invoke-Git -Args @('log', '--oneline', $range)
    $names = Invoke-Git -Args @('diff', '--name-status', $range)

    $notesFile = Join-Path $OutputDir ("deploy-notes_{0}_{1}.md" -f $Mode, $Timestamp)
    $zipFile   = Join-Path $OutputDir ("{0}_{1}_{2}_{3}.zip" -f $PluginSlug, $Version, $Mode, $Timestamp)

    $notes = @()
    $notes += "# Deploy notes ($Mode)"
    $notes += ""
    $notes += "- Plugin: $PluginSlug"
    $notes += "- Version: $Version"
    $notes += "- Git root: $gitRoot"
    $notes += "- HEAD: $head"
    if ($baselineTag) { $notes += "- Latest baseline tag: $baselineTag" }
    if ($releaseTag) { $notes += "- Latest release tag: $releaseTag" }
    $notes += "- Diff range: $range"
    $notes += ""
    if ($status.Trim()) {
        $notes += "## Working tree status (UNCOMMITTED CHANGES DETECTED)"
        $notes += ""
        $notes += '```'
        $notes += (Split-Lines -Text $status)
        $notes += '```'
        $notes += ""
    }
    $notes += "## Changed files"
    $notes += ""
    $notes += '```'
    $notes += (Split-Lines -Text $names)
    $notes += '```'
    $notes += ""
    $notes += "## Commits"
    $notes += ""
    $notes += '```'
    $notes += (Split-Lines -Text $log)
    $notes += '```'
    $notes += ""
    $notes += "## Manual upload"
    $notes += ""
    $notes += "- WP Admin → Plugins → Add New → Upload Plugin → pilih ZIP → Activate"
    $notes += "- ZIP path: $zipFile"
    $notes += "- Notes path: $notesFile"
    $notes += ""

    $notes -join "`n" | Set-Content -LiteralPath $notesFile -Encoding UTF8

    # Stage & zip to guarantee ZIP contains folder `ptsbi-premium/` at archive root.
    Add-Type -AssemblyName System.IO.Compression.FileSystem | Out-Null
    $tempBase = Join-Path ([System.IO.Path]::GetTempPath()) ("ptsbi-premium-stage-{0}" -f ([guid]::NewGuid().ToString('N')))
    $stageRoot = Join-Path $tempBase 'stage'
    $stagePlugin = Join-Path $stageRoot $PluginSlug
    New-Item -ItemType Directory -Force -Path $stagePlugin | Out-Null

    # Copy plugin files into staging area (exclude local artifacts and common noise).
    $excludeDirs = @('releases-local', '.git', '.github', '.vscode', '.idea', 'node_modules')
    $excludeArgs = @()
    foreach ($d in $excludeDirs) { $excludeArgs += @('/XD', $d) }

    $rc = & robocopy $PluginRoot $stagePlugin /MIR /R:1 /W:1 /NFL /NDL /NJH /NJS /NP @excludeArgs
    if ($LASTEXITCODE -ge 8) {
        throw "robocopy failed with exit code $LASTEXITCODE"
    }

    if (Test-Path -LiteralPath $zipFile) { Remove-Item -LiteralPath $zipFile -Force }
    [System.IO.Compression.ZipFile]::CreateFromDirectory($stageRoot, $zipFile)

    Write-Host "OK"
    Write-Host "ZIP:   $zipFile"
    Write-Host "NOTES: $notesFile"
}
finally {
    Pop-Location
}

