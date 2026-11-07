#!/usr/bin/env powershell
$file = "C:\xampp\htdocs\Bank-Syariah-Indonesia\dashboard-pendataan\sql\999_fresh_install_full.sql"
$database = "database_pendataan-bsi"
$host = "127.0.0.1"
$user = "root"

$content = Get-Content $file
$commands = $content -split ';'
foreach ($cmd in $commands) {
    $cmd = $cmd.Trim()
    if ($cmd -and $cmd -notmatch '^--') {
        try {
            & "C:\xampp\mysql\bin\mysql.exe" -u $user $database -e "$cmd"
        } catch {
            # Ignore errors for commands that might fail (like IF NOT EXISTS)
        }
    }
}