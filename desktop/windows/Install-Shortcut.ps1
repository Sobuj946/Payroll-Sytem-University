<#
  Makes a "Payroll Management System" shortcut on the Desktop and in the Start menu.
  Run once: right-click this file and choose "Run with PowerShell".
#>
$ErrorActionPreference = 'Stop'

$ProjectDir = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Launcher = Join-Path $PSScriptRoot 'launch.ps1'
$Icon = Join-Path $ProjectDir 'desktop\icons\payroll.ico'
$Shell = New-Object -ComObject WScript.Shell

$places = @(
    [Environment]::GetFolderPath('Desktop'),
    (Join-Path ([Environment]::GetFolderPath('Programs')) '')
)

foreach ($folder in $places) {
    $link = $Shell.CreateShortcut((Join-Path $folder 'Payroll Management System.lnk'))
    $link.TargetPath = 'powershell.exe'
    $link.Arguments = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$Launcher`""
    $link.WorkingDirectory = $ProjectDir
    $link.IconLocation = $Icon
    $link.Description = 'Payroll Management System'
    $link.WindowStyle = 7
    $link.Save()
}

Write-Host 'Done. Look for "Payroll Management System" on your Desktop and in the Start menu.'
