# Builds an upload-ready zip for cPanel (new.mtctruckparts.com).
# Usage (from project root):  powershell -ExecutionPolicy Bypass -File deploy\build-cpanel.ps1
param(
    [string]$AppUrl = 'https://new.mtctruckparts.com',
    [switch]$SkipDatabase
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$buildDir = Join-Path $PSScriptRoot 'build\mtc'
$zipPath = Join-Path $PSScriptRoot ("mtc-cpanel-" + (Get-Date -Format 'yyyyMMdd-HHmm') + '.zip')

Write-Host "Cleaning $buildDir"
if (Test-Path $buildDir) { Remove-Item $buildDir -Recurse -Force }
New-Item -ItemType Directory -Path $buildDir -Force | Out-Null

# /XJ skips junctions (public\storage must be recreated on the server with storage:link)
$excludeDirs = @(
    (Join-Path $root 'node_modules'),
    (Join-Path $root '.git'),
    (Join-Path $root 'deploy'),
    (Join-Path $root 'tests'),
    (Join-Path $root 'vendor'),
    (Join-Path $root 'storage\logs'),
    (Join-Path $root 'storage\framework\cache'),
    (Join-Path $root 'storage\framework\sessions'),
    (Join-Path $root 'storage\framework\views'),
    (Join-Path $root 'storage\app\private\livewire-tmp')
)
$excludeFiles = @('.env', '.env.backup', '*.log')

Write-Host 'Copying project files...'
robocopy $root $buildDir /E /XJ /NFL /NDL /NJH /NJS /NP /XD $excludeDirs /XF $excludeFiles | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with code $LASTEXITCODE" }

# Local caches reference dev-only packages (pail, collision) and Windows paths
Get-ChildItem (Join-Path $buildDir 'bootstrap\cache') -Filter '*.php' -ErrorAction SilentlyContinue | Remove-Item -Force

if ($SkipDatabase) {
    Remove-Item (Join-Path $buildDir 'database\database.sqlite') -ErrorAction SilentlyContinue
}

foreach ($dir in @('storage\logs', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'bootstrap\cache')) {
    New-Item -ItemType Directory -Path (Join-Path $buildDir $dir) -Force | Out-Null
}

# Fallback for hosts that cannot point the subdomain document root at /public
$rootHtaccess = @'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(\.env|\.git|composer\.(json|lock)|artisan|database|storage|vendor|bootstrap|config|app|routes|resources)(/|$) - [F,L,NC]
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
'@
Set-Content -Path (Join-Path $buildDir '.htaccess') -Value $rootHtaccess -Encoding ascii

Write-Host 'Writing production .env...'
$envLines = Get-Content (Join-Path $root '.env')
$overrides = [ordered]@{
    'APP_ENV'               = 'production'
    'APP_DEBUG'             = 'false'
    'APP_URL'               = $AppUrl
    'APP_NOINDEX'           = 'true'
    'LOG_LEVEL'             = 'error'
    'SESSION_SECURE_COOKIE' = 'true'
    'MAIL_FROM_ADDRESS'     = '"info@mtctruckparts.com"'
}
$seen = @{}
$out = foreach ($line in $envLines) {
    $key = ($line -split '=', 2)[0].Trim()
    if ($overrides.Contains($key)) { $seen[$key] = $true; "$key=$($overrides[$key])" } else { $line }
}
foreach ($key in $overrides.Keys) { if (-not $seen[$key]) { $out += "$key=$($overrides[$key])" } }
Set-Content -Path (Join-Path $buildDir '.env') -Value $out -Encoding UTF8

Write-Host 'Installing production dependencies (no dev)...'
Push-Location $buildDir
try {
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress
    if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }
} finally {
    Pop-Location
}

Write-Host "Creating $zipPath"
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
tar -a -c -f $zipPath -C $buildDir .
if ($LASTEXITCODE -ne 0) { throw 'zip failed' }

$sizeMb = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
Write-Host "Done: $zipPath ($sizeMb MB)"
