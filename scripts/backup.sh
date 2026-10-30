#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."

PHP_BIN="${PHP_BIN:-php}"
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-mysqldump}"
BACKUP_DIR="${BACKUP_DIR:-storage/app/backups}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"

[[ -f .env ]] || { echo 'File .env tidak ditemukan.' >&2; exit 1; }
command -v "$PHP_BIN" >/dev/null
command -v "$MYSQLDUMP_BIN" >/dev/null
command -v tar >/dev/null
command -v gzip >/dev/null
[[ "$BACKUP_RETENTION_DAYS" =~ ^[0-9]+$ ]] || { echo 'BACKUP_RETENTION_DAYS harus berupa angka.' >&2; exit 1; }

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
BACKUP_DIR_ABS="$(cd "$BACKUP_DIR" && pwd -P)"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
PREFIX="$BACKUP_DIR_ABS/dthree-$STAMP"
MYSQL_OPTIONS="$(mktemp)"
DATABASE_NAME_FILE="$(mktemp)"
cleanup(){ rm -f "$MYSQL_OPTIONS" "$DATABASE_NAME_FILE"; }
trap cleanup EXIT
chmod 600 "$MYSQL_OPTIONS" "$DATABASE_NAME_FILE"

BACKUP_MYSQL_OPTIONS="$MYSQL_OPTIONS" BACKUP_DATABASE_NAME="$DATABASE_NAME_FILE" "$PHP_BIN" -r '
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = config("database.connections.".config("database.default"));
if (($connection["driver"] ?? null) !== "mysql") { fwrite(STDERR, "Backup otomatis saat ini membutuhkan koneksi MySQL.\n"); exit(1); }
$escape = static function ($value): string {
    $value = (string) $value;
    if (str_contains($value, "\n") || str_contains($value, "\r")) { throw new RuntimeException("Konfigurasi database tidak valid."); }
    return "\"".str_replace(["\\", "\""], ["\\\\", "\\\""], $value)."\"";
};
$options = "[client]\n";
foreach (["host" => "host", "port" => "port", "username" => "user", "password" => "password", "unix_socket" => "socket"] as $configKey => $option) {
    if (!empty($connection[$configKey])) { $options .= $option."=".$escape($connection[$configKey])."\n"; }
}
file_put_contents(getenv("BACKUP_MYSQL_OPTIONS"), $options);
file_put_contents(getenv("BACKUP_DATABASE_NAME"), (string) ($connection["database"] ?? ""));
'

DATABASE_NAME="$(cat "$DATABASE_NAME_FILE")"
[[ "$DATABASE_NAME" =~ ^[A-Za-z0-9_.-]+$ ]] || { echo 'Nama database tidak valid untuk backup.' >&2; exit 1; }

"$MYSQLDUMP_BIN" --defaults-extra-file="$MYSQL_OPTIONS" --single-transaction --quick --triggers --no-tablespaces --default-character-set=utf8mb4 --databases "$DATABASE_NAME" | gzip -9 > "$PREFIX-database.sql.gz"
tar --exclude='app/backups' -czf "$PREFIX-storage.tar.gz" -C storage app
chmod 600 "$PREFIX-database.sql.gz" "$PREFIX-storage.tar.gz"

if command -v sha256sum >/dev/null; then
    sha256sum "$PREFIX-database.sql.gz" "$PREFIX-storage.tar.gz" > "$PREFIX-SHA256SUMS"
    chmod 600 "$PREFIX-SHA256SUMS"
fi

"$PHP_BIN" artisan system:backup-record "$(basename "$PREFIX")" --no-interaction
find "$BACKUP_DIR_ABS" -maxdepth 1 -type f -name 'dthree-*' -mtime "+$BACKUP_RETENTION_DAYS" -delete
echo "Backup selesai: $PREFIX"
