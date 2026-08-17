#!/bin/bash

###############################################################################
# OND System - Deployment Script (Update & Pull Latest Changes)
# Usage: bash deploy.sh
###############################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Get app path
if [ -z "$APP_PATH" ]; then
    APP_PATH=$(pwd)
fi

if [ -z "$APP_USER" ]; then
    APP_USER="ond"
fi

log_info "Deploy directory: $APP_PATH"
log_info "App user: $APP_USER"

cd $APP_PATH

# Check if in git repo
if [ ! -d .git ]; then
    log_error "Tidak ada .git folder. Pastikan berada di aplikasi directory"
    exit 1
fi

# git dijalankan sebagai root (lewat sudo) di atas direktori milik $APP_USER —
# tanpa ini git menolak semua perintah dengan "detected dubious ownership".
git config --global --add safe.directory "$APP_PATH"

# chmod -R 755 di step permission (di bawah) mengubah execute-bit SEMUA file,
# termasuk yang git lacak sebagai non-executable (blade, php, json, dst).
# Tanpa core.fileMode=false, git status/diff akan menganggap seluruh repo
# "modified" walau isinya sama persis — pernah bikin bingung membedakan
# perubahan asli dari noise permission.
git config core.fileMode false

if [ -t 0 ]; then
    read -p "Lanjutkan deploy? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "Deploy dibatalkan"
        exit 1
    fi
fi

###############################################################################
# 1. Backup Database (sebelum kode berubah, bukan sesudah)
###############################################################################
log_info "=== 1. Backup Database ==="
BACKUP_DIR="$APP_PATH/backups"
mkdir -p $BACKUP_DIR
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/db_backup_${TIMESTAMP}.sql"

# -f2- (bukan -f2) supaya password yang kebetulan mengandung karakter '='
# tidak terpotong saat dibaca ulang dari .env — lihat catatan yang sama di
# setup.sh soal DB_PASS.
DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d '=' -f 2-)
DB_USER=$(grep "^DB_USERNAME=" .env | cut -d '=' -f 2-)
DB_PASS=$(grep "^DB_PASSWORD=" .env | cut -d '=' -f 2-)

# --no-tablespaces: user aplikasi (ond_app) sengaja dibatasi hanya ke
# database sendiri, tanpa privilege PROCESS global — tanpa flag ini,
# mysqldump di MySQL 8 gagal dengan "Access denied ... PROCESS privilege".
mysqldump --no-tablespaces -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > $BACKUP_FILE
log_success "Database backed up: $BACKUP_FILE"

###############################################################################
# 2. Sync Kode ke origin (bukan git pull biasa)
###############################################################################
log_info "=== 2. Sync ke origin ==="
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
git fetch origin

# reset --hard (bukan pull/merge) supaya server selalu persis sama dengan
# origin — git pull bisa konflik atau diam-diam tidak fast-forward kalau ada
# perubahan lokal di server (mis. file sempat ditempel manual saat darurat).
# Deployment target seharusnya tidak pernah punya riwayat sendiri yang perlu
# dipertahankan; kalau ada perubahan yang ingin disimpan, commit & push dulu
# SEBELUM menjalankan script ini.
git reset --hard "origin/$CURRENT_BRANCH"
log_success "Code updated to $(git rev-parse --short HEAD)"

# git di atas jalan sebagai root (lewat sudo bash deploy.sh) dan file yang
# disentuhnya jadi milik root — composer/npm di bawah jalan sebagai
# $APP_USER dan akan gagal tulis kalau ownership tidak dikembalikan dulu.
chown -R $APP_USER:$APP_USER $APP_PATH

###############################################################################
# 3. Install Dependencies
###############################################################################
log_info "=== 3. Install PHP Dependencies ==="
sudo -u $APP_USER composer install --no-dev --optimize-autoloader
log_success "PHP dependencies updated"

log_info "=== 4. Install Node Dependencies ==="
sudo -u $APP_USER npm install
log_success "Node dependencies updated"

###############################################################################
# 5. Build Assets
###############################################################################
log_info "=== 5. Build Assets ==="
sudo -u $APP_USER npm run build
log_success "Assets built"

###############################################################################
# 6. Run Migrations
###############################################################################
log_info "=== 6. Run Database Migrations ==="
RUN_MIGRATE=y
if [ -t 0 ]; then
    read -p "Run migrations? (y/n): " -n 1 -r
    echo
    RUN_MIGRATE=$REPLY
fi
if [[ $RUN_MIGRATE =~ ^[Yy]$ ]]; then
    sudo -u $APP_USER php artisan migrate --force
    log_success "Migrations completed"
else
    log_info "Migrations skipped"
fi

###############################################################################
# 6b. Permission (urutan ini penting — lihat catatan di bawah)
###############################################################################
log_info "=== 6b. Fix Permissions ==="

# chmod -R 755 dulu, BARU .env dikunci 600 setelahnya — kalau dibalik,
# recursive chmod di atas akan menimpa ulang .env jadi 755 (bisa dibaca
# semua user, padahal isinya DB_PASSWORD dan APP_KEY). Ini bug nyata yang
# pernah terjadi di setup.sh sebelum diperbaiki.
chmod -R 755 $APP_PATH
chmod -R 775 $APP_PATH/storage
chmod -R 775 $APP_PATH/bootstrap/cache
chmod 600 $APP_PATH/.env
[ -f $APP_PATH/.env.backup ] && chmod 600 $APP_PATH/.env.backup

# PHP-FPM jalan sebagai www-data, bukan $APP_USER. Tanpa www-data ikut
# grup $APP_USER, request lewat browser gagal tulis ke storage/framework
# (tempnam() 500 error) meski command artisan CLI selalu normal karena
# jalan sebagai $APP_USER langsung. usermod -aG aman dipanggil berulang.
usermod -aG $APP_USER www-data

log_success "Permissions fixed"

###############################################################################
# 7. Clear Cache
###############################################################################
log_info "=== 7. Clear Cache ==="
sudo -u $APP_USER php artisan cache:clear
sudo -u $APP_USER php artisan config:clear
log_success "Cache cleared"

###############################################################################
# 8. Optimize
###############################################################################
log_info "=== 8. Optimize ==="
sudo -u $APP_USER php artisan config:cache
sudo -u $APP_USER php artisan route:cache
sudo -u $APP_USER php artisan view:cache
log_success "Optimized"

###############################################################################
# 9. Restart Services
###############################################################################
log_info "=== 9. Restart Services ==="
systemctl restart php8.3-fpm
systemctl reload nginx
supervisorctl restart ond-queue:*
log_success "Services restarted"

###############################################################################
# Summary
###############################################################################
echo ""
echo "================================================================================"
echo -e "${GREEN}✓ DEPLOY SELESAI!${NC}"
echo "================================================================================"
echo ""
echo "Aplikasi: $APP_PATH"
echo "Branch: $CURRENT_BRANCH"
echo "Backup: $BACKUP_FILE"
echo ""
echo "Check status:"
echo "  systemctl status php8.3-fpm"
echo "  systemctl status nginx"
echo "  supervisorctl status ond-queue:*"
echo ""
echo "Tail logs:"
echo "  tail -f $APP_PATH/storage/logs/laravel.log"
echo ""
echo "================================================================================"
