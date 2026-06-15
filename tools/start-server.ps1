param(
    [int]$Port = 8011
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$phpCandidates = @(
    "E:\xampp\php\php.exe",
    "D:\xampp\php\php.exe",
    "C:\xampp\php\php.exe"
)

$php = $null
foreach ($candidate in $phpCandidates) {
    if (Test-Path $candidate) {
        $php = $candidate
        break
    }
}

if (-not $php) {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) {
        $php = $cmd.Source
    }
}

if (-not $php) {
    throw "PHP executable not found. Install XAMPP or add php to PATH."
}

$busyPorts = @(8010, 8017, 8020, 8021)
if ($Port -in $busyPorts) {
    throw "Port $Port is reserved/busy. Use a free port such as 8011 or 8018."
}

$listening = netstat -ano | Select-String "LISTENING" | Select-String ":$Port "
if ($listening) {
    throw "Port $Port is already in use."
}

Set-Location $root

if (Test-Path (Join-Path $root "artisan")) {
    Write-Host "Starting Laravel dev server on http://127.0.0.1:$Port"
    & $php artisan serve --host=127.0.0.1 --port=$Port
    exit $LASTEXITCODE
}

$public = Join-Path $root "public"
if (-not (Test-Path $public)) {
    New-Item -ItemType Directory -Path $public | Out-Null
}

$router = Join-Path $public "router.php"
if (-not (Test-Path $router)) {
    @'
<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

require __DIR__ . '/index.php';
'@ | Set-Content -Path $router -Encoding UTF8
}

Write-Host "Starting PHP built-in server on http://127.0.0.1:$Port"
& $php -S "127.0.0.1:$Port" -t $public $router
