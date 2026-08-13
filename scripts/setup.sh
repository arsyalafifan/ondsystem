#!/bin/bash

###############################################################################
# OND System - Jagoan Web VPS Setup Script
# Ubuntu 22.04 LTS
# Usage: bash setup.sh
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    log_error "Script harus dijalankan sebagai root. Gunakan: sudo bash setup.sh"
    exit 1
fi

log_info "Memulai setup OND System di Jagoan Web VPS..."
log_info "OS: Ubuntu 22.04 LTS"

# Get user input
read -p "Masukkan nama domain (contoh: ondsystem.com): " DOMAIN
read -p "Masukkan username aplikasi (default: ond): " APP_USER
APP_USER=${APP_USER:-ond}

read -p "Masukkan password MySQL root (minimum 12 karakter): " MYSQL_ROOT_PASS
if [ ${#MYSQL_ROOT_PASS} -lt 12 ]; then
    log_error "Password terlalu pendek (minimal 12 karakter)"
    exit 1
fi

read -p "Masukkan GitHub repository URL (contoh: https://github.com/user/ondsystem.git): " REPO_URL
read -p "Masukkan branch yang akan di-deploy (default: main): " GIT_BRANCH
GIT_BRANCH=${GIT_BRANCH:-main}

read -p "Masukkan email superadmin (default: superadmin@$DOMAIN): " SUPERADMIN_EMAIL
SUPERADMIN_EMAIL=${SUPERADMIN_EMAIL:-superadmin@$DOMAIN}

read -s -p "Masukkan password superadmin (kosongkan untuk dibangkitkan otomatis): " SUPERADMIN_PASSWORD
echo
if [ -z "$SUPERADMIN_PASSWORD" ]; then
    SUPERADMIN_PASSWORD=$(openssl rand -base64 16 | tr -d '=+/' | cut -c1-16)
    log_warn "Password superadmin dibangkitkan otomatis (dicatat di ringkasan akhir)"
fi

APP_PATH="/var/www/$DOMAIN"

log_info "Konfigurasi:"
log_info "  Domain: $DOMAIN"
log_info "  App User: $APP_USER"
log_info "  App Path: $APP_PATH"
log_info "  Git Branch: $GIT_BRANCH"
log_info "  Superadmin: $SUPERADMIN_EMAIL"

read -p "Lanjutkan? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_error "Setup dibatalkan"
    exit 1
fi

###############################################################################
# 1. Update System
###############################################################################
log_info "=== 1. Update System ==="
apt-get update
apt-get upgrade -y
apt-get install -y curl wget git zip unzip software-properties-common

log_success "System updated"

###############################################################################
# 2. Install PHP 8.3
###############################################################################
log_info "=== 2. Install PHP 8.3 ==="
add-apt-repository ppa:ondrej/php -y
apt-get update
apt-get install -y \
    php8.3-fpm \
    php8.3-cli \
    php8.3-curl \
    php8.3-mysql \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-intl \
    php8.3-gd \
    php8.3-redis \
    php8.3-zip \
    php8.3-bcmath \
    php8.3-dev

# Enable PHP extensions
phpenmod -v 8.3 mbstring xml intl gd redis bcmath

# Set PHP configuration
php_config="/etc/php/8.3/fpm/php.ini"
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 50M/' $php_config
sed -i 's/post_max_size = 8M/post_max_size = 50M/' $php_config
sed -i 's/memory_limit = 128M/memory_limit = 512M/' $php_config

systemctl restart php8.3-fpm

log_success "PHP 8.3 installed"

###############################################################################
# 3. Install MySQL
###############################################################################
log_info "=== 3. Install MySQL ==="
DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server

mysql -u root << MYSQL_SCRIPT
ALTER USER 'root'@'localhost' IDENTIFIED BY '$MYSQL_ROOT_PASS';
FLUSH PRIVILEGES;
MYSQL_SCRIPT

log_success "MySQL installed"

###############################################################################
# 4. Install Redis
###############################################################################
log_info "=== 4. Install Redis ==="
apt-get install -y redis-server

systemctl enable redis-server
systemctl start redis-server

log_success "Redis installed"

###############################################################################
# 5. Install Nginx
###############################################################################
log_info "=== 5. Install Nginx ==="
apt-get install -y nginx

systemctl enable nginx

log_success "Nginx installed"

###############################################################################
# 6. Install Node.js & npm
###############################################################################
log_info "=== 6. Install Node.js & npm ==="
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt-get install -y nodejs

log_success "Node.js installed"

###############################################################################
# 7. Install Composer
###############################################################################
log_info "=== 7. Install Composer ==="
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

log_success "Composer installed"

###############################################################################
# 8. Create Application User
###############################################################################
log_info "=== 8. Create Application User ==="
if ! id "$APP_USER" &>/dev/null; then
    useradd -m -s /bin/bash $APP_USER
    log_success "User $APP_USER created"
else
    log_warn "User $APP_USER sudah ada"
fi

###############################################################################
# 9. Setup Application Directory
###############################################################################
log_info "=== 9. Setup Application Directory ==="
mkdir -p $APP_PATH
cd $APP_PATH

# Clone repository
log_info "Cloning repository dari $REPO_URL..."
git clone -b $GIT_BRANCH $REPO_URL $APP_PATH
chown -R $APP_USER:$APP_USER $APP_PATH
chmod -R 755 $APP_PATH

log_success "Repository cloned"

###############################################################################
# 10. Install PHP Dependencies
###############################################################################
log_info "=== 10. Install PHP Dependencies ==="
cd $APP_PATH
sudo -u $APP_USER composer install --no-dev --optimize-autoloader

log_success "PHP dependencies installed"

###############################################################################
# 11. Install Node Dependencies & Build Assets
###############################################################################
log_info "=== 11. Install Node Dependencies ==="
cd $APP_PATH
sudo -u $APP_USER npm install
sudo -u $APP_USER npm run build

log_success "Assets built"

###############################################################################
# 12. Setup Environment
###############################################################################
log_info "=== 12. Setup Environment ==="
cd $APP_PATH

if [ -f .env ]; then
    log_warn ".env sudah ada, membuat backup ke .env.backup"
    cp .env .env.backup
fi

# Create .env file
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
# Jalankan scripts/setup-osrm.sh untuk OSRM self-hosted (Docker), lalu ganti
# OSRM_URL ke http://127.0.0.1:5000 dan isi OSRM_FALLBACK_URL dengan baris
# di atas sebagai cadangan.
OSRM_FALLBACK_URL=
NOMINATIM_EMAIL=$DOMAIN

TRUSTED_PROXIES=127.0.0.1,::1

# Dari .env.example
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
# 13. Generate Application Key
###############################################################################
log_info "=== 13. Generate Application Key ==="
sudo -u $APP_USER php artisan key:generate

log_success "Application key generated"

###############################################################################
# 14. Create Database & User
###############################################################################
log_info "=== 14. Create Database & User ==="
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
# 15. Run Database Migrations
###############################################################################
log_info "=== 15. Run Database Migrations ==="
cd $APP_PATH
sudo -u $APP_USER php artisan migrate --force

log_success "Migrations completed"

###############################################################################
# 15b. Seed Superadmin (bukan data contoh)
###############################################################################
log_info "=== 15b. Seed Akun Superadmin ==="
cd $APP_PATH
sudo -u $APP_USER env SUPERADMIN_EMAIL="$SUPERADMIN_EMAIL" SUPERADMIN_PASSWORD="$SUPERADMIN_PASSWORD" \
    php artisan db:seed --class="Database\\Seeders\\SuperadminSeeder" --force

log_success "Superadmin dibuat: $SUPERADMIN_EMAIL"

###############################################################################
# 16. Setup Storage & Permissions
###############################################################################
log_info "=== 16. Setup Storage & Permissions ==="
cd $APP_PATH
sudo -u $APP_USER php artisan storage:link

# Set proper permissions
chmod -R 755 $APP_PATH
chmod -R 775 $APP_PATH/storage
chmod -R 775 $APP_PATH/bootstrap/cache

log_success "Storage configured"

###############################################################################
# 17. Configure Nginx
###############################################################################
log_info "=== 17. Configure Nginx ==="

# Backup original config
cp /etc/nginx/sites-available/default /etc/nginx/sites-available/default.bak

# Create new config
cat > /etc/nginx/sites-available/$DOMAIN << 'NGINX_CONFIG'
server {
    listen 80;
    listen [::]:80;
    server_name DOMAIN_PLACEHOLDER;

    root APP_PATH_PLACEHOLDER/public;
    index index.php index.html;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name DOMAIN_PLACEHOLDER;

    root APP_PATH_PLACEHOLDER/public;
    index index.php index.html;

    # SSL certificates (placeholder)
    ssl_certificate /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css text/xml text/javascript
               application/x-javascript application/xml+rss
               application/json application/javascript;

    # Client upload limit
    client_max_body_size 50M;

    # Logs
    access_log /var/log/nginx/DOMAIN_PLACEHOLDER-access.log;
    error_log /var/log/nginx/DOMAIN_PLACEHOLDER-error.log;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_intercept_errors on;
        fastcgi_param HTTPS on;
    }

    # Deny access to .env and other sensitive files
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

# Replace placeholders
sed -i "s|DOMAIN_PLACEHOLDER|$DOMAIN|g" /etc/nginx/sites-available/$DOMAIN
sed -i "s|APP_PATH_PLACEHOLDER|$APP_PATH|g" /etc/nginx/sites-available/$DOMAIN

# Enable site
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/$DOMAIN

# Test Nginx config
nginx -t

# Reload Nginx
systemctl reload nginx

log_success "Nginx configured"

###############################################################################
# 18. Setup SSL Certificate (Let's Encrypt)
###############################################################################
log_info "=== 18. Setup SSL Certificate ==="
apt-get install -y certbot python3-certbot-nginx

# Get certificate
certbot certonly --nginx -d $DOMAIN --non-interactive --agree-tos -m admin@$DOMAIN

log_success "SSL certificate installed"

###############################################################################
# 19. Setup Supervisor for Queue Worker
###############################################################################
log_info "=== 19. Setup Supervisor ==="
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
# 20. Setup Cron Job for Laravel Scheduler
###############################################################################
log_info "=== 20. Setup Cron Job ==="
(sudo -u $APP_USER crontab -l 2>/dev/null; echo "* * * * * cd $APP_PATH && php artisan schedule:run >> /dev/null 2>&1") | sudo -u $APP_USER crontab -

log_success "Cron job setup"

###############################################################################
# 21. Optimize Laravel
###############################################################################
log_info "=== 21. Optimize Laravel ==="
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
echo "Informasi Aplikasi:"
echo "  URL: https://$DOMAIN"
echo "  Path: $APP_PATH"
echo "  User: $APP_USER"
echo ""
echo "Informasi Database:"
DB_NAME=${DOMAIN//./_}
echo "  Database: $DB_NAME"
echo "  User: ond_app"
echo "  Password: (disimpan di .env)"
echo ""
echo "Informasi MySQL Root:"
echo "  Password: $MYSQL_ROOT_PASS"
echo "  (Simpan di tempat aman!)"
echo ""
echo "Akun Superadmin (satu-satunya akun yang dibuat):"
echo "  Email: $SUPERADMIN_EMAIL"
echo "  Password: $SUPERADMIN_PASSWORD"
echo "  (Simpan sekarang — tidak ditampilkan ulang. Segera login dan ganti password.)"
echo ""
echo "Next Steps:"
echo "  1. Buka https://$DOMAIN di browser"
echo "  2. Login dengan akun superadmin di atas"
echo "  3. Daftarkan admin, sales, dan driver lewat aplikasi"
echo ""
echo "Monitoring:"
echo "  Logs: $APP_PATH/storage/logs/"
echo "  Queue: sudo supervisorctl status"
echo "  PHP-FPM: sudo systemctl status php8.3-fpm"
echo "  MySQL: sudo systemctl status mysql"
echo "  Nginx: sudo systemctl status nginx"
echo ""
echo "Certificate Renewal:"
echo "  Sudah setup otomatis via certbot"
echo ""
echo "================================================================================"
