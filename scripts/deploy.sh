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

read -p "Lanjutkan deploy? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_error "Deploy dibatalkan"
    exit 1
fi

###############################################################################
# 1. Git Pull
###############################################################################
log_info "=== 1. Pull Latest Changes ==="
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
git pull origin $CURRENT_BRANCH
log_success "Code updated"

###############################################################################
# 2. Backup Database
###############################################################################
log_info "=== 2. Backup Database ==="
BACKUP_DIR="$APP_PATH/backups"
mkdir -p $BACKUP_DIR
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/db_backup_${TIMESTAMP}.sql"

DB_NAME=$(grep "DB_DATABASE=" .env | cut -d '=' -f 2)
DB_USER=$(grep "DB_USERNAME=" .env | cut -d '=' -f 2)
DB_PASS=$(grep "DB_PASSWORD=" .env | cut -d '=' -f 2)

mysqldump -u $DB_USER -p"$DB_PASS" $DB_NAME > $BACKUP_FILE
log_success "Database backed up: $BACKUP_FILE"

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
read -p "Run migrations? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    sudo -u $APP_USER php artisan migrate --force
    log_success "Migrations completed"
else
    log_info "Migrations skipped"
fi

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
