# Payroll Management System: desktop use

The system is a normal web application that runs on one computer. The files in this folder make it behave like a desktop program:
you double-click an icon, the system starts in its own window (no address bar, no `http://localhost...` to type), and when you close the window everything stops again.

Nothing here changes the application itself, and nothing needs the internet once the setup below is done.

## What is needed

| | Windows | Linux (Mint, Ubuntu) |
|---|---|---|
| PHP 8.2 or newer | with **XAMPP** or **Laragon** (either one includes PHP and MySQL) | `sudo apt install php php-cli php-mysql php-mbstring php-xml php-curl php-zip php-bcmath php-gd` |
| MySQL 8 | in XAMPP / Laragon, or the MySQL installer | `sudo apt install mysql-server` |
| Composer | getcomposer.org | `sudo apt install composer` |
| A Chrome-type browser | Microsoft Edge (already in Windows) or Google Chrome | Chrome, Chromium, Brave or Edge |

If no Chrome-type browser is found, the system opens in your normal browser instead.

## First-time setup (once per computer)

Open a terminal in the project folder (on Windows: PowerShell, with `php` and `composer` on the PATH, or use XAMPP's/Laragon's terminal).

**1. Install the program's libraries**
```
composer install --no-dev --optimize-autoloader
```

**2. Create the database and a database user** (change the password)
```
mysql -u root -p
CREATE DATABASE payroll_pms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pms'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT ALL ON payroll_pms.* TO 'pms'@'localhost';
EXIT;
```
On Linux use `sudo mysql` instead of `mysql -u root -p`. With XAMPP the first command is `C:\xampp\mysql\bin\mysql -u root`.

**3. Create the settings file**
- Copy `.env.desktop.example` to a new file called `.env`.
- Set `DB_PASSWORD` to the password you chose.
- Then run: `php artisan key:generate`

**4. Create the tables, the basic data and the first administrator**
```
php artisan pms:install
```
It asks for the administrator's name, email and password, and the organization's name.
To try the system with demonstration employees, add `--demo`: `php artisan pms:install --demo` (the demo accounts all use the password `Password@123`, so never use `--demo` for real data).

**5. Download the page design files once** (this is what makes it work without internet)
```
php artisan pms:install-assets
```

**6. Check the settings**
```
php artisan pms:security-check
```
Clear any warning it prints.

**7. Make the icon**
- Windows: right-click `desktop\windows\Install-Shortcut.ps1` and choose *Run with PowerShell*. A "Payroll Management System" icon appears on the Desktop and in the Start menu.
- Linux: `bash desktop/linux/install.sh`. The icon appears in the applications menu and on the Desktop.

Then open **Settings** inside the system and enter the organization's address, phone, email and logo, the holidays, and the tax slabs.
The tax slabs that come with the system are **sample rates**; replace them with the rules your organization follows.

## Daily use

Double-click **Payroll Management System**. After a few seconds the window opens.
Close the window to stop the system (the database is stopped too if the launcher started it).

On Windows you can also double-click `desktop\windows\Start-Payroll.bat`; on Linux run `desktop/linux/launch.sh`.

## Backups

Use **Backup & Export** inside the system (Administrator only) to download a full backup file.
Keep copies on another disk, because a backup contains salaries and bank details.
Photos and the logo are stored in `public/uploads`; copy that folder too.

## Moving to another computer

1. Make a backup in the system and copy `public/uploads`.
2. On the new computer do the first-time setup above, but **skip** `pms:install`.
3. Restore the backup: `mysql -u pms -p payroll_pms < payroll-backup-....sql`
4. Put the `uploads` folder back into `public/uploads`.

## If something does not start

| What you see | What to do |
|---|---|
| "PHP was not found" | Install XAMPP or Laragon, or install PHP and make sure `php` works in a terminal. To use a PHP in an unusual place, set the environment variable `PAYROLL_PHP` to the full path of `php.exe` / `php`. |
| "The database could not be started" | Start MySQL from the XAMPP or Laragon control panel (Windows), or run `sudo systemctl start mysql` (Linux). |
| "The system is not set up yet" | The `.env` file is missing. Follow step 3. |
| The window opens but shows an error page | Look at the end of `storage/logs/laravel.log`. For startup problems the launcher also writes `%LOCALAPPDATA%\PayrollApp\server.log` (Windows) or `~/.cache/payroll-app/server.log` (Linux). |
| The page looks plain, without the design | Run `php artisan pms:install-assets` (step 5). |
| Port 8123 is already in use | Set the environment variable `PAYROLL_PORT` (Linux) or change `$Port` at the top of `launch.ps1` (Windows). |

## How it works

- The launcher checks that MySQL is running (starting it if it can), starts Laravel's built-in web server on `127.0.0.1:8123` (reachable from this computer only, not from the network), and opens that address in a Chrome-type browser in "app" mode with its own profile, so it looks like a program window and waiting for that window to close really waits for *this* window.
- When the window closes, the launcher stops the web server it started.
- Because the page files are installed locally, the server sends a strict content security policy, so the browser refuses to run any script that does not come from the system itself.

## What this is not

This is not a single installer file. The computer still needs PHP and MySQL, which XAMPP or Laragon provide in one step on Windows.
A one-file installer would require a heavy extra tool (such as Electron) and a second programming language to maintain; the project brief asked for the simplest reliable approach, so it was not used.
