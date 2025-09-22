param(
  [Parameter(Mandatory=$true)][string]$RepoUrl,
  [Parameter(Mandatory=$true)][string]$TargetDir,
  [string]$Branch = "main"
)

$ErrorActionPreference = 'Stop'

Write-Host "[1/6] Cloning repo: $RepoUrl (branch: $Branch)"
if (Test-Path "$TargetDir/.git") {
  git -C $TargetDir fetch --all --prune
  git -C $TargetDir checkout $Branch
  git -C $TargetDir reset --hard "origin/$Branch"
} else {
  New-Item -ItemType Directory -Force -Path $TargetDir | Out-Null
  git clone --branch $Branch --depth 1 $RepoUrl $TargetDir
}

Set-Location $TargetDir

Write-Host "[2/6] Creating runtime directories"
New-Item -ItemType Directory -Force -Path logs | Out-Null
New-Item -ItemType Directory -Force -Path database | Out-Null

Write-Host "[3/6] Permissions"
icacls logs /grant *S-1-1-0:(OI)(CI)M /T | Out-Null
icacls database /grant *S-1-1-0:(OI)(CI)M /T | Out-Null

Write-Host "[4/6] PHP extensions check (pdo_sqlite required)"
php -r "exit(extension_loaded('pdo_sqlite')?0:1);"
if ($LASTEXITCODE -ne 0) { throw 'PHP extension pdo_sqlite is not enabled' }

Write-Host "[5/6] DB bootstrap"
New-Item -ItemType File -Force -Path database\xeray.db | Out-Null

Write-Host "[6/6] Start local server (Ctrl+C to stop)"
Write-Host "Open: http://localhost:8080"
php -S localhost:8080 -t public


