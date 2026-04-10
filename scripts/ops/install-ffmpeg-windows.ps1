$ErrorActionPreference = 'Stop'

param(
    [string]$InstallDir = "$env:LOCALAPPDATA\Programs\FFmpeg\bin"
)

$packageId = 'Gyan.FFmpeg'

Write-Host "Instalando $packageId via winget..."
winget install --id $packageId --accept-source-agreements --accept-package-agreements

$wingetRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
$ffmpegSource = Get-ChildItem -Path $wingetRoot -Recurse -Filter ffmpeg.exe | Sort-Object LastWriteTime -Descending | Select-Object -First 1
$ffprobeSource = Get-ChildItem -Path $wingetRoot -Recurse -Filter ffprobe.exe | Sort-Object LastWriteTime -Descending | Select-Object -First 1

if (-not $ffmpegSource -or -not $ffprobeSource) {
    throw 'Nao foi possivel localizar ffmpeg.exe e ffprobe.exe apos a instalacao.'
}

New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null

$ffmpegTarget = Join-Path $InstallDir 'ffmpeg.exe'
$ffprobeTarget = Join-Path $InstallDir 'ffprobe.exe'

Copy-Item -LiteralPath $ffmpegSource.FullName -Destination $ffmpegTarget -Force
Copy-Item -LiteralPath $ffprobeSource.FullName -Destination $ffprobeTarget -Force

Write-Host ''
Write-Host 'Configure no ambiente do apps/api:'
Write-Host "MEDIA_FFMPEG_BIN=$ffmpegTarget"
Write-Host "MEDIA_FFPROBE_BIN=$ffprobeTarget"
Write-Host ''
Write-Host 'Depois valide com:'
Write-Host 'cd apps/api'
Write-Host 'php artisan media:tooling-status'
