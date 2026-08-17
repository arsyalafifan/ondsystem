#!/bin/bash

###############################################################################
# OND System - Backup Script (Database & Files)
# Usage: bash backup.sh
# Bisa dijadwalkan via cron: 0 2 * * * cd /var/www/domain.com && bash backup.sh
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

# Configuration
APP_PATH="${1:-.}"
BACKUP_DIR="$APP_PATH/backups"
RETENTION_DAYS=30
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

log_info "Memulai backup..."
log_info "Backup directory: $BACKUP_DIR"

# Get database credentials from .env
if [ ! -f "$APP_PATH/.env" ]; then
    log_error ".env tidak ditemukan"
    exit 1
fi

# -f2- (bukan -f2) supaya nilai yang kebetulan mengandung karakter '='
# (mis. password base64) tidak terpotong saat dibaca ulang dari .env.
DB_NAME=$(grep "^DB_DATABASE=" $APP_PATH/.env | cut -d '=' -f 2-)
DB_USER=$(grep "^DB_USERNAME=" $APP_PATH/.env | cut -d '=' -f 2-)
DB_PASS=$(grep "^DB_PASSWORD=" $APP_PATH/.env | cut -d '=' -f 2-)
DB_HOST=$(grep "^DB_HOST=" $APP_PATH/.env | cut -d '=' -f 2- | tr -d ' ')

###############################################################################
# 1. Backup Database
###############################################################################
log_info "=== Backup Database ==="
DB_BACKUP="$BACKUP_DIR/db_${TIMESTAMP}.sql.gz"

if mysqldump --no-tablespaces -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > $DB_BACKUP; then
    DB_SIZE=$(du -h $DB_BACKUP | cut -f1)
    log_success "Database backup: $DB_BACKUP ($DB_SIZE)"
else
    log_error "Gagal backup database"
    exit 1
fi

###############################################################################
# 2. Backup Storage (Foto & Files)
###############################################################################
log_info "=== Backup Storage ==="
STORAGE_BACKUP="$BACKUP_DIR/storage_${TIMESTAMP}.tar.gz"

if tar -czf $STORAGE_BACKUP -C $APP_PATH storage/app/public 2>/dev/null; then
    STORAGE_SIZE=$(du -h $STORAGE_BACKUP | cut -f1)
    log_success "Storage backup: $STORAGE_BACKUP ($STORAGE_SIZE)"
else
    log_warn "Storage backup gagal atau kosong"
fi

###############################################################################
# 3. Backup Config (.env, composer.lock)
###############################################################################
log_info "=== Backup Config ==="
CONFIG_BACKUP="$BACKUP_DIR/config_${TIMESTAMP}.tar.gz"

tar -czf $CONFIG_BACKUP \
    -C $APP_PATH \
    .env \
    composer.lock \
    package-lock.json \
    2>/dev/null || true

CONFIG_SIZE=$(du -h $CONFIG_BACKUP | cut -f1)
log_success "Config backup: $CONFIG_BACKUP ($CONFIG_SIZE)"

###############################################################################
# 4. Cleanup Old Backups
###############################################################################
log_info "=== Cleanup Old Backups ==="
DELETED_COUNT=$(find $BACKUP_DIR -type f -mtime +$RETENTION_DAYS -delete | wc -l)
log_success "Deleted $DELETED_COUNT backup files older than $RETENTION_DAYS days"

###############################################################################
# 5. Summary
###############################################################################
TOTAL_SIZE=$(du -sh $BACKUP_DIR | cut -f1)

echo ""
echo "================================================================================"
echo -e "${GREEN}✓ BACKUP SELESAI${NC}"
echo "================================================================================"
echo ""
echo "Backup Location: $BACKUP_DIR"
echo "Total Backup Size: $TOTAL_SIZE"
echo "Retention Period: $RETENTION_DAYS days"
echo ""
echo "Backup Files:"
ls -lh $BACKUP_DIR | tail -5
echo ""
echo "================================================================================"
