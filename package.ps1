$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$out = Join-Path $root 'dist'
$stage = Join-Path $out 'ticket-site'
if (Test-Path $out) { Remove-Item $out -Recurse -Force }
New-Item -ItemType Directory -Path $stage | Out-Null

foreach ($dir in 'src', 'lib', 'views', 'public', 'config') {
    Copy-Item (Join-Path $root $dir) (Join-Path $stage $dir) -Recurse
}
New-Item -ItemType Directory -Path (Join-Path $stage 'bin') | Out-Null
foreach ($file in 'bin\check.php', 'bin\sync.php', 'bin\.htaccess', '.htaccess', 'README.md') {
    Copy-Item (Join-Path $root $file) (Join-Path $stage $file)
}
Remove-Item (Join-Path $stage 'config\config.local.php') -ErrorAction SilentlyContinue

$storage = Join-Path $stage 'storage'
foreach ($sub in 'cache', 'db', 'logs', 'payments', 'sessions') {
    New-Item -ItemType Directory -Path (Join-Path $storage $sub) -Force | Out-Null
}
Copy-Item (Join-Path $root 'storage\.htaccess') (Join-Path $storage '.htaccess')

$zip = Join-Path $out "ticket-site.zip"
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($stage, $zip)
Get-Item $zip | Select-Object FullName, Length
