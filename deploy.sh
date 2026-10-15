#!/usr/bin/env bash
#
# Deploy lspp-be ke server.
#
# Pemakaian (di server):
#   cd ~/dev.lspp306.com
#   bash deploy.sh                # deploy branch staging (default)
#   BRANCH=main bash deploy.sh    # deploy branch lain
#
# Variabel opsional:
#   PHP=/opt/cpanel/ea-php83/root/usr/bin/php   binary PHP kalau `php` bukan 8.3
#   COMPOSER=composer                           perintah composer
#   BUILD_ASSETS=1                              jalankan npm ci && npm run build
#   SKIP_BACKUP=1                               lewati backup database (tidak disarankan)
#
# Skrip berhenti di error pertama (set -e). Kalau gagal setelah `down`,
# situs tetap dalam maintenance mode sampai diperbaiki lalu `php artisan up`.

set -euo pipefail

BRANCH="${BRANCH:-staging}"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
APP_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/lspp-be}"

cd "$APP_DIR"

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31m!! %s\033[0m\n' "$*" >&2; exit 1; }

# 1. Pastikan working tree server bersih: jangan ada file yang diedit langsung di server.
log "Cek working tree"
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    git status --short --untracked-files=no
    fail "Ada perubahan lokal di server. Commit dari laptop, atau 'git stash' dulu, baru deploy."
fi

# 2. Ambil perubahan dan tampilkan apa yang akan masuk.
log "Fetch origin/$BRANCH"
git fetch origin "$BRANCH"
PREV_COMMIT="$(git rev-parse HEAD)"
if [ "$PREV_COMMIT" = "$(git rev-parse "origin/$BRANCH")" ]; then
    log "Sudah versi terbaru ($PREV_COMMIT). Tidak ada yang di-deploy."
    exit 0
fi
git log --oneline "HEAD..origin/$BRANCH"
echo "Migrasi baru:"
git diff --name-only --diff-filter=A HEAD "origin/$BRANCH" -- database/migrations || true

# 3. Backup database sebelum apa pun diubah.
if [ "${SKIP_BACKUP:-0}" != "1" ]; then
    log "Backup database"
    mkdir -p "$BACKUP_DIR"
    # Kredensial dibaca dari config Laravel (.env), satu nilai per baris.
    { read -r DB_HOST; read -r DB_PORT; read -r DB_NAME; read -r DB_USER; read -r DB_PASS; } < <(
        $PHP artisan tinker --execute='$c = config("database.connections.mysql"); echo implode("\n", [$c["host"], $c["port"], $c["database"], $c["username"], $c["password"]]), "\n";'
    )
    BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_$(date +%Y%m%d_%H%M%S)_${PREV_COMMIT:0:7}.sql.gz"
    MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --routines --no-tablespaces \
        -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" | gzip > "$BACKUP_FILE"
    echo "Backup: $BACKUP_FILE"
    # Simpan 10 backup terakhir saja.
    ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm --
fi

# 4. Maintenance mode.
log "Maintenance mode ON"
$PHP artisan down --retry=30 || true

# 5. Update kode (fast-forward saja, tidak pernah membuat merge commit di server).
log "Pull kode"
git merge --ff-only "origin/$BRANCH"

# 6. Dependency PHP sesuai composer.lock.
log "Composer install"
$COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# 7. Asset frontend CMS (Vite), hanya kalau diminta.
if [ "${BUILD_ASSETS:-0}" = "1" ]; then
    log "Build asset"
    npm ci && npm run build
fi

# 8. Migrasi database.
log "Migrate"
$PHP artisan migrate --force

# 9. Rebuild cache config/route/view/event.
log "Rebuild cache"
$PHP artisan optimize:clear
$PHP artisan optimize

# 10. Worker queue membaca kode baru (QUEUE_CONNECTION=database).
log "Restart queue worker"
$PHP artisan queue:restart

# 11. Selesai.
log "Maintenance mode OFF"
$PHP artisan up

log "Deploy selesai: $PREV_COMMIT -> $(git rev-parse --short HEAD)"
echo "Rollback kode: git reset --hard $PREV_COMMIT && $PHP artisan optimize"
