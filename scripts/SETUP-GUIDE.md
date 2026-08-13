# OND System - Jagoan Web VPS Setup Guide

Panduan lengkap untuk deploy OND System di Jagoan Web VPS dengan Ubuntu 22.04 LTS.

## Prerequisites

- ✅ VPS dari Jagoan Web (minimal 4GB RAM, 2vCPU - paket Pro)
- ✅ OS: Ubuntu 22.04 LTS
- ✅ SSH access (root)
- ✅ Domain sudah di-point ke VPS IP
- ✅ GitHub repository (untuk kloning)

## Persiapan Sebelum Setup

### 1. Connect ke VPS via SSH

```bash
ssh root@[IP_VPS]
```

### 2. Download Script Setup

Pilih salah satu metode:

**Metode A: Clone dari GitHub (recommended)**
```bash
git clone https://github.com/your-repo/ondsystem.git
cd ondsystem/scripts
```

**Metode B: Copy manual via SCP/SFTP**
```bash
# Dari local machine
scp setup.sh root@[IP_VPS]:/root/
scp deploy.sh root@[IP_VPS]:/root/
scp backup.sh root@[IP_VPS]:/root/
scp monitor.sh root@[IP_VPS]:/root/
```

### 3. Buat SSH Key untuk Deploy Otomatis (Optional)

Jika ingin auto-deploy dari GitHub:

```bash
ssh-keygen -t ed25519 -f /root/.ssh/github_deploy
# Tambahkan public key ke GitHub repository > Settings > Deploy keys
cat /root/.ssh/github_deploy.pub
```

---

## Quick Start (Recommended)

### Step 1: Jalankan Setup Script

```bash
cd /root
chmod +x setup.sh
sudo bash setup.sh
```

**Input yang diperlukan:**
- Domain (contoh: ondsystem.com)
- App user (default: ond)
- MySQL root password (minimal 12 karakter)
- GitHub repository URL
- Git branch (default: main)

Script akan otomatis:
- ✅ Update system
- ✅ Install PHP 8.3, MySQL, Redis, Nginx
- ✅ Install Node.js & Composer
- ✅ Clone repository
- ✅ Setup database & migrations
- ✅ Generate SSL certificate
- ✅ Configure Nginx
- ✅ Setup Supervisor untuk queue worker
- ✅ Optimize Laravel

**Durasi:** ~10-15 menit

### Step 2: Verifikasi Setup

```bash
# Check services
systemctl status nginx
systemctl status php8.3-fpm
systemctl status mysql
systemctl status redis-server

# Check Supervisor queue
supervisorctl status ond-queue:*

# Check logs
tail -f /var/www/[domain]/storage/logs/laravel.log
```

### Step 3: Access Application

Buka di browser: `https://[domain]`

**Login pertama kali:**

Gunakan akun superadmin yang emailnya Anda masukkan saat setup.sh berjalan.
Passwordnya ditampilkan di ringkasan akhir setup.sh (dibangkitkan otomatis
kalau dikosongkan) — catat sekarang karena tidak ditampilkan ulang. Tidak ada
akun demo/dummy yang ikut dibuat di produksi.

Setelah login, buat akun admin, sales, dan driver lewat aplikasi (bukan
seeder).

---

## Post-Setup Configuration

### 1. Update Environment Variables

Edit `.env` untuk customize:

```bash
nano /var/www/[domain]/.env
```

**Penting diubah:**

```env
# Koordinat Depot (Jakarta default)
DEPOT_LAT=-6.175
DEPOT_LNG=106.827
DEPOT_NAMA="Nama Depot Anda"

# Email
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# Lokasi default
APP_LOCALE=id

# OSRM (optional, gunakan publik atau self-hosted)
OSRM_URL=http://router.project-osrm.org
OSRM_ENABLED=true
```

Setelah edit:
```bash
cd /var/www/[domain]
php artisan config:cache
systemctl reload nginx
```

### 1b. OSRM Self-Hosted (Docker) — Opsional tapi Direkomendasikan

Server OSRM publik (`router.project-osrm.org`) punya rate limit dan tanpa
SLA — cukup untuk testing, berisiko untuk volume harian tinggi. Untuk
production, jalankan OSRM sendiri lewat Docker sebagai server **utama**,
dengan server publik sebagai **cadangan** otomatis kalau yang utama down.

```bash
cd /root/scripts   # atau di mana pun setup-osrm.sh berada
sudo bash setup-osrm.sh
```

Script akan meminta:
- Path aplikasi (untuk baca `DEPOT_LAT`/`DEPOT_LNG` dari `.env`)
- Pulau sumber data (Geofabrik hanya menyediakan ekstrak per-pulau untuk
  Indonesia, bukan per-provinsi — pilih pulau tempat depot berada)
- Radius clip di sekitar depot dalam km (default 150km, kira-kira setara
  luas satu provinsi)

**Kenapa di-clip, bukan pakai data seluruh Indonesia:** ekstrak seluruh
Indonesia butuh ~4-6GB RAM saat diproses dan ~1.5-2.5GB RAM saat berjalan —
terlalu berat untuk VPS 4GB yang juga menjalankan PHP/MySQL/Redis. Hasil
clip radius 150km di sekitar Jakarta misalnya hanya perlu ~300-500MB RAM
resident, karena OND System pada dasarnya beroperasi di sekitar satu depot.

**Setelah script selesai**, update `.env`:

```env
OSRM_URL=http://127.0.0.1:5000
OSRM_FALLBACK_URL=https://router.project-osrm.org
OSRM_ENABLED=true
```

```bash
php artisan config:cache
systemctl restart php8.3-fpm
```

**Perilaku dua jalur:** mesin routing mencoba `OSRM_URL` (Docker lokal)
lebih dulu. Kalau container itu tidak bisa dihubungi, otomatis mencoba
`OSRM_FALLBACK_URL` (server publik). Kalau keduanya gagal, mesin routing
jatuh ke perhitungan garis lurus (haversine × faktor kelokan) — rute tetap
terbentuk, halaman memberi tahu admin bahwa angkanya perkiraan. Tidak ada
titik kegagalan tunggal.

**Monitoring container:**
```bash
docker ps --filter name=ond-osrm
docker logs -f ond-osrm
docker stats ond-osrm --no-stream   # cek pemakaian RAM aktual
```

**Kalau nanti area operasional meluas** (mis. keluar dari radius yang
dipilih), jalankan ulang `setup-osrm.sh` dengan pulau atau radius yang lebih
besar — data lama otomatis ditimpa.

### 2. Setup Email (SMTP)

Edit `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

### 3. Setup Backup Otomatis

Add cron job untuk backup harian (jam 2 pagi):

```bash
# Edit crontab
crontab -e

# Tambahkan
0 2 * * * cd /var/www/[domain] && bash backup.sh >> /var/log/ond-backup.log 2>&1
```

Test manual:
```bash
cd /var/www/[domain]
bash backup.sh
ls -lh backups/
```

### 4. Setup Monitoring Alerts (Optional)

Monitor resource usage:

```bash
# Install htop (optional)
apt-get install -y htop

# Run monitoring
bash /var/www/[domain]/monitor.sh
```

Setup alerting jika CPU/Memory > threshold:

```bash
# Add to crontab (check setiap 5 menit)
*/5 * * * * /usr/local/bin/check-resources.sh
```

### 5. Data Awal (Toko, Produk, Wilayah)

Data ini **bukan** dari seeder di produksi — `SuperadminSeeder` yang
dijalankan setup.sh hanya membuat satu akun superadmin, sengaja tanpa data
contoh. `php artisan db:seed` (tanpa `--class`) menjalankan
`DatabaseSeeder` penuh yang berisi toko/produk/pengguna dummy untuk
demo — **jangan dijalankan di produksi**, karena akan mencampur data asli
dengan data contoh.

Untuk mengisi data asli, gunakan superadmin yang sudah login:

- **Wilayah & Produk** — input manual lewat halaman Master.
- **Toko** — input manual, atau **impor CSV** (kolom `latitude`/`longitude`
  didukung) lewat halaman Master Toko. Lihat bagian "Melengkapi koordinat
  toko" di [README.md](../README.md) root aplikasi.

Kalau butuh mengulang dari nol di environment staging/testing (bukan
produksi berisi data asli):

```bash
cd /var/www/[domain]
php artisan migrate:fresh --seed --force   # HANYA untuk staging, menghapus semua data
```

---

## Daily Operations

### Deploy Update Terbaru

```bash
cd /var/www/[domain]
bash deploy.sh
```

Script akan:
- ✅ Pull latest code dari Git
- ✅ Backup database
- ✅ Update composer & npm dependencies
- ✅ Build assets
- ✅ Run migrations
- ✅ Clear & optimize cache
- ✅ Restart services

### Monitor Status

```bash
bash /var/www/[domain]/monitor.sh
```

### View Logs

```bash
# Laravel application log
tail -f /var/www/[domain]/storage/logs/laravel.log

# Queue worker log
supervisorctl tail ond-queue:0 -f

# Nginx log
tail -f /var/log/nginx/[domain]-error.log

# PHP-FPM log
tail -f /var/log/php8.3-fpm.log
```

### Restart Services

```bash
# Restart PHP-FPM
systemctl restart php8.3-fpm

# Reload Nginx
systemctl reload nginx

# Restart Queue Worker
supervisorctl restart ond-queue:*

# Check Supervisor status
supervisorctl status
```

---

## Troubleshooting

### 1. Page Blank / 500 Error

```bash
cd /var/www/[domain]

# Check permissions
chmod -R 755 .
chmod -R 775 storage bootstrap/cache

# Clear cache
php artisan cache:clear
php artisan config:clear

# Check logs
tail -50 storage/logs/laravel.log
```

### 2. Queue Worker Not Running

```bash
# Check status
supervisorctl status ond-queue:*

# Restart
supervisorctl restart ond-queue:*

# Check config
supervisorctl reread
supervisorctl update

# View logs
supervisorctl tail ond-queue:0
```

### 3. Database Connection Error

```bash
# Test MySQL connection
mysql -u ond_app -p[password] -e "SELECT 1;"

# Check .env
cat /var/www/[domain]/.env | grep DB_

# Restart MySQL
systemctl restart mysql
```

### 4. Storage/Upload Issues

```bash
cd /var/www/[domain]

# Check storage link
ls -la public/storage

# Recreate if missing
rm public/storage
php artisan storage:link

# Fix permissions
chmod -R 775 storage
```

### 5. SSL Certificate Expired

```bash
# Auto-renewal check
certbot renew --dry-run

# Manual renewal
certbot renew

# Check certificate
certbot certificates
```

### 6. High Memory/CPU Usage

```bash
# Check processes
top
htop

# Check queue backlog
cd /var/www/[domain]
php artisan queue:monitor redis:default,redis:failed

# Scale queue workers
# Edit /etc/supervisor/conf.d/ond-queue.conf
# Change: numprocs=2 (atau lebih)
# Reload: supervisorctl reread && supervisorctl update
```

---

## Backup & Recovery

### Manual Backup

```bash
cd /var/www/[domain]
bash backup.sh
ls -lh backups/
```

### Restore from Backup

```bash
cd /var/www/[domain]

# Restore database
DB_NAME=$(grep "DB_DATABASE=" .env | cut -d '=' -f 2)
DB_USER=$(grep "DB_USERNAME=" .env | cut -d '=' -f 2)
DB_PASS=$(grep "DB_PASSWORD=" .env | cut -d '=' -f 2)

gunzip < backups/db_[timestamp].sql.gz | mysql -u $DB_USER -p"$DB_PASS" $DB_NAME

# Restore storage
tar -xzf backups/storage_[timestamp].tar.gz
```

### Backup to Cloud (Optional)

Setup automated backup ke AWS S3 atau Google Cloud:

```bash
# Install AWS CLI
apt-get install -y awscli

# Configure credentials
aws configure

# Add to backup script atau crontab:
aws s3 cp /var/www/[domain]/backups/ s3://your-bucket/ --recursive
```

---

## Performance Optimization

### 1. PHP-FPM Tuning

Edit `/etc/php/8.3/fpm/pool.d/www.conf`:

```conf
pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 2
pm.max_spare_servers = 10
pm.max_requests = 500
```

Restart: `systemctl restart php8.3-fpm`

### 2. MySQL Optimization

```bash
# Edit /etc/mysql/my.cnf
max_connections = 100
innodb_buffer_pool_size = 1G
query_cache_size = 64M
```

Restart: `systemctl restart mysql`

### 3. Nginx Caching

Update Nginx config `/etc/nginx/sites-available/[domain]`:

```nginx
# Add caching header
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    expires 30d;
    add_header Cache-Control "public, immutable";
}
```

### 4. Database Indexing

Buat cronjob untuk optimize tables:

```bash
# Add to crontab (weekly)
0 3 * * 0 cd /var/www/[domain] && php artisan db:optimize-table
```

---

## Security Hardening

### 1. Firewall Setup (UFW)

```bash
# Enable firewall
ufw enable

# Allow SSH
ufw allow 22

# Allow HTTP/HTTPS
ufw allow 80/tcp
ufw allow 443/tcp

# Check status
ufw status
```

### 2. Fail2Ban (Prevent Brute Force)

```bash
apt-get install -y fail2ban

# Create config
cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local

# Edit untuk Laravel login
# systemctl restart fail2ban
```

### 3. Disable Root SSH

Edit `/etc/ssh/sshd_config`:

```conf
PermitRootLogin no
```

Restart: `systemctl restart sshd`

### 4. Regular Security Updates

```bash
# Weekly updates
apt-get update && apt-get upgrade -y

# Add to crontab
0 2 * * 0 apt-get update && apt-get upgrade -y
```

---

## Useful Commands

```bash
# View PHP version
php -v

# View Composer version
composer -V

# View Node version
node -v && npm -v

# Check disk usage
df -h
du -sh /var/www/[domain]

# Check network connections
netstat -tuln

# View all cron jobs
crontab -l

# View supervisor config
supervisorctl help

# Test domain DNS
nslookup [domain]

# Test SSL certificate
openssl s_client -connect [domain]:443

# Count active connections
netstat -an | grep ESTABLISHED | wc -l
```

---

## Support & References

- **Laravel Docs**: https://laravel.com/docs
- **Livewire Docs**: https://livewire.laravel.com
- **Supervisor Docs**: http://supervisord.org
- **Let's Encrypt**: https://letsencrypt.org
- **MySQL Docs**: https://dev.mysql.com

---

## Checklist After Setup

- [ ] Domain accessible via HTTPS
- [ ] Database initialized dengan seeder
- [ ] Queue worker running
- [ ] Backup script setup
- [ ] Email configured (if needed)
- [ ] Monitoring setup
- [ ] SSL auto-renewal configured
- [ ] Firewall rules configured
- [ ] Cron jobs scheduled
- [ ] Regular deployment tested

---

**Last Updated**: 2026-08-13
