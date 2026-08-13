#!/bin/bash

###############################################################################
# OND System - Lanjutan setup.sh
#
# Dipakai kalau setup.sh sempat gagal di tengah jalan (mis. npm run build
# gagal karena Node.js terlalu lama) sehingga sistem, PHP, MySQL, Redis,
# Nginx, Node, composer install, dan npm build SUDAH selesai — tinggal
# env, database, migrasi, seed superadmin, Nginx, SSL, dan queue worker.
#
# Usage: sudo bash resume-setup.sh
###############################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error()   { echo -e "${RED}[ERROR]${NC} $1"; }

if [[ $EUID -ne 0 ]]; then
    log_error "Jalankan sebagai root: sudo bash resume-setup.sh"
    exit 1
fi

###############################################################################
# Input
###############################################################################
read -p "Domain (default: icechoco-ondsystem.id): " DOMAIN
DOMAIN=${DOMAIN:-icechoco-ondsystem.id}

read -p "App user (default: ond): " APP_USER
APP_USER=${APP_USER:-ond}

APP_PATH="/var/www/$DOMAIN"

if [ ! -d "$APP_PATH" ]; then
    log_error "$APP_PATH tidak ditemukan. Pastikan repo sudah di-clone di sini."
    exit 1
fi

if [ ! -d "$APP_PATH/node_modules" ] || [ ! -d "$APP_PATH/public/build" ]; then
    log_error "npm install / npm run build sepertinya belum selesai di $APP_PATH."
    log_error "Jalankan dulu: cd $APP_PATH && npm install && npm run build"
    exit 1
fi

read -p "Masukkan password MySQL root (yang tadi dibuat): " MYSQL_ROOT_PASS
if [ ${#MYSQL_ROOT_PASS} -lt 12 ]; then
    log_error "Password terlalu pendek — pastikan ini password MySQL root yang benar"
    exit 1
fi

read -p "Masukkan email superadmin (default: superadmin@$DOMAIN): " SUPERADMIN_EMAIL
SUPERADMIN_EMAIL=${SUPERADMIN_EMAIL:-superadmin@$DOMAIN}

read -s -p "Masukkan password superadmin (kosongkan untuk dibangkitkan otomatis): " SUPERADMIN_PASSWORD
echo
if [ -z "$SUPERADMIN_PASSWORD" ]; then
    SUPERADMIN_PASSWORD=$(openssl rand -base64 16 | tr -d '=+/' | cut -c1-16)
    log_warn "Password superadmin dibangkitkan otomatis (dicatat di ringkasan akhir)"
fi

echo ""
log_info "Konfigurasi:"
log_info "  Domain: $DOMAIN"
log_info "  App Path: $APP_PATH"
log_info "  App User: $APP_USER"
log_info "  Superadmin: $SUPERADMIN_EMAIL"
echo ""
read -p "Lanjutkan? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_error "Dibatalkan"
    exit 1
fi

###############################################################################
# 1. Setup Environment
###############################################################################
log_info "=== 1. Setup Environment ==="
cd $APP_PATH

if [ -f .env ]; then
    log_warn ".env sudah ada, membuat backup ke .env.backup"
    cp .env .env.backup
fi

cat > .env << EOF
APP_NAME="OND System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://$DOMAIN

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DOMAIN//./_}
DB_USERNAME=ond_app
DB_PASSWORD=$(openssl rand -base64 32)

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
BROADCAST_DRIVER=log

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@$DOMAIN
MAIL_FROM_NAME="OND System"

OSRM_ENABLED=true
OSRM_URL=http://router.project-osrm.org
OSRM_FALLBACK_URL=
NOMINATIM_EMAIL=$DOMAIN

TRUSTED_PROXIES=127.0.0.1,::1

ROUTING_MAX_TOKO=25
ROUTING_MAX_DUS=220
ROUTING_MIN_DUS_PER_TOKO=5
DEPOT_LAT=-6.175
DEPOT_LNG=106.827
DEPOT_NAMA="Depot Jakarta"
DEPOT_SERVICE_MINUTES=5
DEPOT_JAM_BERANGKAT="08:00"
VISIT_MAKS_TOKO_PER_SALES=120
VISIT_JARAK_WAJAR_M=300
VISIT_FOTO_LEBAR_MAKS=1280
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
EOF

chown $APP_USER:$APP_USER .env
chmod 600 .env

log_success "Environment configured"

###############################################################################
# 2. Generate Application Key
###############################################################################
log_info "=== 2. Generate Application Key ==="
sudo -u $APP_USER php artisan key:generate

log_success "Application key generated"

###############################################################################
# 3. Create Database & User
###############################################################################
log_info "=== 3. Create Database & User ==="
DB_NAME=${DOMAIN//./_}
DB_USER="ond_app"
DB_PASS=$(grep "DB_PASSWORD" $APP_PATH/.env | cut -d '=' -f 2)

mysql -u root -p"$MYSQL_ROOT_PASS" << MYSQL_SCRIPT
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
MYSQL_SCRIPT

log_success "Database created"

###############################################################################
# 4. Run Database Migrations
###############################################################################
log_info "=== 4. Run Database Migrations ==="
cd $APP_PATH
sudo -u $APP_USER php artisan migrate --force

log_success "Migrations completed"

###############################################################################
# 5. Seed Superadmin
###############################################################################
log_info "=== 5. Seed Akun Superadmin ==="
cd $APP_PATH
sudo -u $APP_USER env SUPERADMIN_EMAIL="$SUPERADMIN_EMAIL" SUPERADMIN_PASSWORD="$SUPERADMIN_PASSWORD" \
    php artisan db:seed --class="Database\\Seeders\\SuperadminSeeder" --force

log_success "Superadmin dibuat: $SUPERADMIN_EMAIL"

###############################################################################
# 6. Setup Storage & Permissions
###############################################################################
log_info "=== 6. Setup Storage & Permissions ==="
cd $APP_PATH
sudo -u $APP_USER php artisan storage:link

chmod -R 755 $APP_PATH
chmod -R 775 $APP_PATH/storage
chmod -R 775 $APP_PATH/bootstrap/cache

log_success "Storage configured"

###############################################################################
# 7. Configure Nginx
###############################################################################
log_info "=== 7. Configure Nginx ==="

cp /etc/nginx/sites-available/default /etc/nginx/sites-available/default.bak 2>/dev/null || true

# Config HTTP-only dulu — sertifikat SSL belum ada di titik ini. certbot
# --nginx di step berikutnya yang menyisipkan blok SSL setelah sertifikatnya
# benar-benar dibuat (menulis config dengan ssl_certificate langsung akan
# membuat `nginx -t` di bawah gagal karena filenya belum ada).
cat > /etc/nginx/sites-available/$DOMAIN << NGINX_CONFIG
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    root $APP_PATH/public;
    index index.php index.html;

    gzip on;
    gzip_types text/plain text/css text/xml text/javascript
               application/x-javascript application/xml+rss
               application/json application/javascript;

    client_max_body_size 50M;

    access_log /var/log/nginx/$DOMAIN-access.log;
    error_log /var/log/nginx/$DOMAIN-error.log;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_intercept_errors on;
    }

    location ~ /\.env {
        deny all;
    }

    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
NGINX_CONFIG

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/$DOMAIN

nginx -t
systemctl reload nginx

log_success "Nginx configured (HTTP dulu, SSL disisipkan certbot di step berikutnya)"

###############################################################################
# 8. Setup SSL Certificate (Let's Encrypt)
###############################################################################
log_info "=== 8. Setup SSL Certificate ==="
apt-get install -y certbot python3-certbot-nginx

certbot --nginx -d $DOMAIN --non-interactive --agree-tos -m admin@$DOMAIN --redirect

log_success "SSL certificate installed"

###############################################################################
# 9. Setup Supervisor for Queue Worker
###############################################################################
log_info "=== 9. Setup Supervisor ==="
apt-get install -y supervisor

cat > /etc/supervisor/conf.d/ond-queue.conf << EOF
[program:ond-queue]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_PATH/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
redirect_stderr=true
stdout_logfile=$APP_PATH/storage/logs/queue.log
user=$APP_USER
directory=$APP_PATH
EOF

supervisorctl reread
supervisorctl update
supervisorctl start ond-queue:*

log_success "Supervisor configured"

###############################################################################
# 10. Setup Cron Job
###############################################################################
log_info "=== 10. Setup Cron Job ==="
(sudo -u $APP_USER crontab -l 2>/dev/null; echo "* * * * * cd $APP_PATH && php artisan schedule:run >> /dev/null 2>&1") | sudo -u $APP_USER crontab -

log_success "Cron job setup"

###############################################################################
# 11. Optimize Laravel
###############################################################################
log_info "=== 11. Optimize Laravel ==="
cd $APP_PATH
sudo -u $APP_USER php artisan config:cache
sudo -u $APP_USER php artisan route:cache
sudo -u $APP_USER php artisan view:cache

log_success "Laravel optimized"

###############################################################################
# Summary
###############################################################################
echo ""
echo "================================================================================"
echo -e "${GREEN}✓ SETUP SELESAI!${NC}"
echo "================================================================================"
echo ""
echo "URL: https://$DOMAIN"
echo ""
echo "Akun Superadmin:"
echo "  Email: $SUPERADMIN_EMAIL"
echo "  Password: $SUPERADMIN_PASSWORD"
echo "  (Simpan sekarang — tidak ditampilkan ulang.)"
echo ""
echo "MySQL Root Password: $MYSQL_ROOT_PASS"
echo ""
echo "Cek status:"
echo "  sudo supervisorctl status"
echo "  sudo systemctl status nginx php8.3-fpm mysql redis-server"
echo ""
echo "================================================================================"
