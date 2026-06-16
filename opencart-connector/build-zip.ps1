$packageRoot = $PSScriptRoot
$uploadRoot = Join-Path $packageRoot "upload"
$zipPath = Join-Path (Split-Path $packageRoot -Parent) "ibs-opencart-sync-connector-v2.ocmod.zip"

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

function Add-ToZip($zipArchive, $sourcePath, $entryName) {
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zipArchive, $sourcePath, $entryName) | Out-Null
}

Add-ToZip $zip (Join-Path $packageRoot "install.xml") "install.xml"
Add-ToZip $zip (Join-Path $packageRoot "install.json") "install.json"

Get-ChildItem -Path $uploadRoot -Recurse -File | ForEach-Object {
    $relative = $_.FullName.Substring("$uploadRoot\".Length).Replace('\', '/')
    Add-ToZip $zip $_.FullName $relative
}

$zip.Dispose()
Write-Host "Built $zipPath"
