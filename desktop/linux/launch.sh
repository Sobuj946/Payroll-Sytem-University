#!/usr/bin/env bash
# Payroll Management System: starts what it needs and opens it in its own window.
# When the window is closed, the system stops again.
# Use the launcher made by install.sh, or run this file.

PORT="${PAYROLL_PORT:-8123}"
PHP_BIN="${PAYROLL_PHP:-php}"
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
URL="http://127.0.0.1:${PORT}"
STATE="${XDG_CACHE_HOME:-$HOME/.cache}/payroll-app"
LOG="$STATE/server.log"
mkdir -p "$STATE"

say() {   # shows a message in a window if possible, and always prints it
    echo "$1"
    if command -v zenity >/dev/null 2>&1; then zenity --error --title="Payroll Management System" --text="$1" 2>/dev/null
    elif command -v notify-send >/dev/null 2>&1; then notify-send "Payroll Management System" "$1"; fi
}

port_open() { (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; }

wait_port() {
    for _ in $(seq 1 $(( $2 * 2 ))); do
        port_open "$1" && return 0
        sleep 0.5
    done
    return 1
}

command -v "$PHP_BIN" >/dev/null 2>&1 || { say "PHP was not found. Install it with: sudo apt install php php-cli php-mysql php-mbstring php-xml php-curl php-zip php-bcmath php-gd"; exit 1; }
[ -f "$DIR/.env" ] || { say "The system is not set up yet (the .env file is missing). See desktop/README.md, 'First-time setup'."; exit 1; }

# ---------- database ----------
if ! port_open 3306; then
    for unit in mysql mariadb; do
        if systemctl list-unit-files "$unit.service" >/dev/null 2>&1; then
            if command -v pkexec >/dev/null 2>&1; then pkexec systemctl start "$unit" 2>/dev/null
            else systemctl start "$unit" 2>/dev/null || sudo -n systemctl start "$unit" 2>/dev/null; fi
            wait_port 3306 20 && break
        fi
    done
fi
port_open 3306 || { say "The database (MySQL) is not running. Start it with: sudo systemctl start mysql"; exit 1; }

# ---------- the system ----------
SERVER_PID=""
if ! port_open "$PORT"; then
    cd "$DIR" || exit 1
    setsid "$PHP_BIN" artisan serve --host=127.0.0.1 --port="$PORT" >"$LOG" 2>&1 &
    SERVER_PID=$!
    trap '[ -n "$SERVER_PID" ] && { kill -TERM -- "-$SERVER_PID" 2>/dev/null || kill "$SERVER_PID" 2>/dev/null; }' EXIT INT TERM
    wait_port "$PORT" 30 || { say "The system did not start. Details: $LOG"; exit 1; }
fi

# ---------- open it in its own window (no address bar) ----------
for browser in google-chrome google-chrome-stable chromium chromium-browser brave-browser microsoft-edge; do
    if command -v "$browser" >/dev/null 2>&1; then
        # a separate profile makes this a window of its own, so waiting for it really waits for this window
        "$browser" --app="$URL" --user-data-dir="$STATE/window-profile" --window-size=1366,820 --no-first-run --disable-features=Translate >/dev/null 2>&1
        exit 0
    fi
done

# no Chrome-type browser: use the default browser and stop when asked
xdg-open "$URL" >/dev/null 2>&1
if command -v zenity >/dev/null 2>&1; then zenity --info --title="Payroll Management System" --text="The system is open in your browser.\nPress OK when you have finished, to stop it." 2>/dev/null
else read -r -p "The system is open in your browser. Press Enter to stop it. " _; fi
