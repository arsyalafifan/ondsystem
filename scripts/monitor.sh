#!/bin/bash

###############################################################################
# OND System - Monitoring Script
# Usage: bash monitor.sh
###############################################################################

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

check_service() {
    if systemctl is-active --quiet $1; then
        echo -e "${GREEN}✓${NC} $1: running"
    else
        echo -e "${RED}✗${NC} $1: STOPPED"
    fi
}

check_port() {
    if netstat -tuln 2>/dev/null | grep -q ":$2 "; then
        echo -e "${GREEN}✓${NC} Port $2 ($1): listening"
    else
        echo -e "${RED}✗${NC} Port $2 ($1): NOT listening"
    fi
}

check_process() {
    if pgrep -f "$1" > /dev/null; then
        COUNT=$(pgrep -f "$1" | wc -l)
        echo -e "${GREEN}✓${NC} $2: $COUNT process(es)"
    else
        echo -e "${RED}✗${NC} $2: NOT running"
    fi
}

clear

echo "================================================================================"
echo -e "${BLUE}OND System - Monitoring Dashboard${NC}"
echo "================================================================================"
echo ""

echo -e "${BLUE}Services:${NC}"
check_service nginx
check_service php8.3-fpm
check_service mysql
check_service redis-server

echo ""
echo -e "${BLUE}Ports:${NC}"
check_port "Nginx HTTP" 80
check_port "Nginx HTTPS" 443
check_port "MySQL" 3306
check_port "Redis" 6379

echo ""
echo -e "${BLUE}Processes:${NC}"
check_process "php artisan queue:work" "Queue Workers"
check_process "php-fpm" "PHP-FPM Workers"

echo ""
echo -e "${BLUE}Resource Usage:${NC}"
echo "Memory:"
free -h | tail -2 | head -1
echo ""
echo "Disk:"
df -h / | tail -1
echo ""

echo -e "${BLUE}Supervisor Status:${NC}"
supervisorctl status ond-queue:* || echo "  Supervisor not available"

echo ""
echo -e "${BLUE}Recent Errors (laravel.log):${NC}"
APP_PATH="${1:-.}"
if [ -f "$APP_PATH/storage/logs/laravel.log" ]; then
    tail -5 "$APP_PATH/storage/logs/laravel.log"
else
    echo "  Log file not found"
fi

echo ""
echo "================================================================================"
echo "Tips:"
echo "  Lihat log real-time: tail -f $APP_PATH/storage/logs/laravel.log"
echo "  Lihat queue status: supervisorctl tail ond-queue:0"
echo "  Restart queue: supervisorctl restart ond-queue:*"
echo "================================================================================"
