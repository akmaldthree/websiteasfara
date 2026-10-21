#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

[[ -f .env ]] || { echo 'Konfigurasi .env dahulu. Lihat docs/HOSTING.md.' >&2; exit 1; }
[[ "$(git branch --show-current)" == 'main' ]] || { echo 'Deployment harus dari branch main.' >&2; exit 1; }
[[ -z "$(git status --porcelain --untracked-files=normal)" ]] || { echo 'Ada perubahan lokal. Periksa sebelum deployment; file tidak ditimpa.' >&2; exit 1; }
command -v "$PHP_BIN" >/dev/null
command -v "$COMPOSER_BIN" >/dev/null
git fetch origin main
git merge-base --is-ancestor HEAD origin/main || { echo 'Branch tidak dapat diperbarui dengan fast-forward.' >&2; exit 1; }
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);'
echo 'Pastikan backup database dan storage terbaru tersedia sebelum menjalankan deployment.'
"$PHP_BIN" artisan down --retry=60
trap 'echo "Deployment gagal. Website tetap maintenance. Periksa log, perbaiki, jalankan ulang; lihat docs/HOSTING.md." >&2' ERR
git pull --ff-only origin main
"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize
"$PHP_BIN" artisan queue:restart
"$PHP_BIN" artisan up
trap - ERR
echo "Deployment selesai: $(git rev-parse --short HEAD)"
