#!/usr/bin/env bash
# Makes "Payroll Management System" appear in the applications menu and on the Desktop.
# Run once:  bash desktop/linux/install.sh
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
chmod +x "$DIR/desktop/linux/launch.sh"

ENTRY="[Desktop Entry]
Type=Application
Name=Payroll Management System
Comment=Employees, attendance, leave, payroll and salary slips
Exec=$DIR/desktop/linux/launch.sh
Icon=$DIR/desktop/icons/payroll.png
Terminal=false
Categories=Office;Finance;"

mkdir -p "$HOME/.local/share/applications"
echo "$ENTRY" > "$HOME/.local/share/applications/payroll-management.desktop"

DESKTOP_DIR="$(xdg-user-dir DESKTOP 2>/dev/null || echo "$HOME/Desktop")"
if [ -d "$DESKTOP_DIR" ]; then
    echo "$ENTRY" > "$DESKTOP_DIR/Payroll Management System.desktop"
    chmod +x "$DESKTOP_DIR/Payroll Management System.desktop"
    gio set "$DESKTOP_DIR/Payroll Management System.desktop" metadata::trusted true 2>/dev/null || true
fi

echo "Done. Look for 'Payroll Management System' in the applications menu and on the Desktop."
