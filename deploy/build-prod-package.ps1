# Build a production package from this repo (Windows + robocopy).
# Usage: powershell -ExecutionPolicy Bypass -File deploy/build-prod-package.ps1 [-Dest C:\xampp\htdocs\1100erp-prod]
param([string]$Dest = 'C:\xampp\htdocs\1100erp-prod')
$ErrorActionPreference = 'Stop'
$src = Split-Path (Split-Path $MyInvocation.MyCommand.Path -Parent) -Parent

# KEEP (needed live or at install time):
#   config.sample.php  <- installer copies it to config.php (DO NOT exclude)
#   maintenance/setup/install-schema.sql <- installer imports it
# DROP: .git, secrets, docs, logs/tmp/exports contents, root restore.php,
#   all *.md, empty database/ leftovers.
robocopy $src $Dest /E `
  /XD .git tmp uploads logs storage exports docs database `
  /XF config.php .env .env.example config.php restore.php php-errors.log *.md
if ($LASTEXITCODE -ge 8) { throw "robocopy failed: $LASTEXITCODE" }

foreach ($d in @('uploads\logo', 'tmp\mpdf', 'storage', 'logs', 'exports')) {
    New-Item -ItemType Directory -Force (Join-Path $Dest $d) | Out-Null
}
foreach ($f in @('storage\.htaccess', 'logs\.htaccess', 'tmp\.htaccess', 'exports\.htaccess')) {
    Copy-Item (Join-Path $src $f) (Join-Path $Dest (Split-Path $f -Parent)) -Force
}
Remove-Item (Join-Path $Dest 'maintenance\setup\lock') -Force -ErrorAction SilentlyContinue

# Ship the upgrade schema at package root (upload + run via phpMyAdmin to
# repair schema drift; System Update page is the preferred path).
Copy-Item (Join-Path $src 'database\upgrade-schema.sql') (Join-Path $Dest 'upgrade-schema.sql') -Force

# Sanity: fail loudly if anything secret or stateful slipped in.
# (config.sample.php is REQUIRED as the installer template, so it is exempt.)
$bad = Get-ChildItem -Path $Dest -Recurse -Include *.md, .env*, config.php, restore.php -File |
    Where-Object { $_.Name -ne 'config.sample.php' }
if ($bad) { throw ("Banned files in package: " + (($bad.FullName) -join ', ')) }
if (Test-Path (Join-Path $Dest '.git')) { throw '.git leaked into package' }
if (-not (Test-Path (Join-Path $Dest 'config.sample.php'))) { throw 'config.sample.php missing from package' }
if (-not (Test-Path (Join-Path $Dest 'maintenance\setup\install-schema.sql'))) { throw 'install-schema.sql missing from package' }
Write-Output "Production package ready at $Dest"
