<#
  Payroll Management System: starts everything it needs and opens it in its own window.
  When the window is closed, the system stops again.

  Normally you do not run this file yourself: use the shortcut made by Install-Shortcut.ps1,
  or double-click Start-Payroll.bat.

  If your PHP or MySQL are installed somewhere unusual, set these in the Settings block below.
#>

# ----------------------------- Settings -----------------------------
$Port = 8123                      # the system listens on this port of this computer only
$PhpPath = $env:PAYROLL_PHP       # leave empty to find PHP automatically (XAMPP, Laragon, or the PATH)
$MysqlDaemon = $env:PAYROLL_MYSQLD # leave empty to find MySQL automatically
# --------------------------------------------------------------------

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms

$ProjectDir = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Url = "http://127.0.0.1:$Port"
$DataDir = Join-Path $env:LOCALAPPDATA 'PayrollApp'
New-Item -ItemType Directory -Force -Path $DataDir | Out-Null
$LogFile = Join-Path $DataDir 'server.log'

function Show-Message($text, $icon = 'Information') {
    [System.Windows.Forms.MessageBox]::Show($text, 'Payroll Management System', 'OK', $icon) | Out-Null
}

function Test-Port($port) {
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $client.Connect('127.0.0.1', $port)
        $client.Close()
        return $true
    } catch {
        return $false
    }
}

function Wait-Port($port, $seconds) {
    for ($i = 0; $i -lt $seconds * 2; $i++) {
        if (Test-Port $port) { return $true }
        Start-Sleep -Milliseconds 500
    }
    return $false
}

function First-Existing($paths) {
    foreach ($p in $paths) { if ($p -and (Test-Path $p)) { return $p } }
    return $null
}

$phpProcess = $null
$mysqlProcess = $null

try {
    # ---------- PHP ----------
    $laragonPhp = Get-ChildItem 'C:\laragon\bin\php\php*\php.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | ForEach-Object { $_.FullName }
    $pathPhp = (Get-Command php -ErrorAction SilentlyContinue).Source
    $Php = First-Existing (@($PhpPath, 'C:\xampp\php\php.exe') + @($laragonPhp) + @($pathPhp))
    if (-not $Php) {
        Show-Message "PHP was not found.`n`nInstall XAMPP or Laragon (with PHP 8.2 or newer), or set PAYROLL_PHP to the full path of php.exe." 'Error'
        exit 1
    }

    if (-not (Test-Path (Join-Path $ProjectDir '.env'))) {
        Show-Message "The system is not set up yet (the .env file is missing).`n`nFollow desktop\README.md, step 'First-time setup'." 'Error'
        exit 1
    }

    # ---------- MySQL ----------
    if (-not (Test-Port 3306)) {
        # 1. a Windows service
        foreach ($svc in (Get-Service -ErrorAction SilentlyContinue | Where-Object { $_.Name -match '^(MySQL|MariaDB)' })) {
            try { Start-Service $svc.Name -ErrorAction Stop } catch { }
            if (Wait-Port 3306 20) { break }
        }
    }
    if (-not (Test-Port 3306)) {
        # 2. start the program directly (XAMPP, Laragon, MySQL installer)
        $laragonMysql = Get-ChildItem 'C:\laragon\bin\mysql\*\bin\mysqld.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | ForEach-Object { $_.FullName }
        $installerMysql = Get-ChildItem 'C:\Program Files\MySQL\MySQL Server *\bin\mysqld.exe' -ErrorAction SilentlyContinue | ForEach-Object { $_.FullName }
        $Mysqld = First-Existing (@($MysqlDaemon, 'C:\xampp\mysql\bin\mysqld.exe') + @($laragonMysql) + @($installerMysql))
        if ($Mysqld) {
            $ini = First-Existing @((Join-Path (Split-Path $Mysqld) 'my.ini'), (Join-Path (Split-Path (Split-Path $Mysqld)) 'my.ini'))
            $mysqlArgs = @('--standalone')
            if ($ini) { $mysqlArgs = @("--defaults-file=`"$ini`"") + $mysqlArgs }
            $mysqlProcess = Start-Process -FilePath $Mysqld -ArgumentList $mysqlArgs -WindowStyle Hidden -PassThru
            Wait-Port 3306 40 | Out-Null
        }
    }
    if (-not (Test-Port 3306)) {
        Show-Message "The database (MySQL) could not be started.`n`nStart it from XAMPP or Laragon, then open the system again." 'Error'
        exit 1
    }

    # ---------- the system itself ----------
    if (-not (Test-Port $Port)) {
        $phpProcess = Start-Process -FilePath $Php -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', "--port=$Port") `
            -WorkingDirectory $ProjectDir -WindowStyle Hidden -PassThru `
            -RedirectStandardOutput $LogFile -RedirectStandardError ($LogFile + '.err')
        if (-not (Wait-Port $Port 30)) {
            Show-Message "The system did not start.`n`nDetails are in:`n$LogFile.err" 'Error'
            exit 1
        }
    }

    # ---------- open it in its own window (no address bar) ----------
    $pf86 = ${env:ProgramFiles(x86)}
    $Browser = First-Existing @(
        "$pf86\Microsoft\Edge\Application\msedge.exe",
        "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
        "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
        "$pf86\Google\Chrome\Application\chrome.exe",
        "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe"
    )

    if ($Browser) {
        # a separate profile makes this a window of its own, so waiting for it really waits for this window
        $windowProfile = Join-Path $DataDir 'window-profile'
        Start-Process -FilePath $Browser -Wait -ArgumentList @("--app=$Url", "--user-data-dir=`"$windowProfile`"", '--window-size=1366,820', '--no-first-run', '--disable-features=Translate')
    } else {
        Start-Process $Url
        Show-Message "The system is open in your browser.`n`nPress OK when you have finished, to stop it."
    }
}
finally {
    # ---------- stop what we started ----------
    if ($phpProcess -and -not $phpProcess.HasExited) { & taskkill /PID $phpProcess.Id /T /F 2>$null | Out-Null }
    if ($mysqlProcess -and -not $mysqlProcess.HasExited) { & taskkill /PID $mysqlProcess.Id /T /F 2>$null | Out-Null }
}
