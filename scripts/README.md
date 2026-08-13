# OND System - Jagoan Web VPS Deployment Scripts

Automation scripts lengkap untuk deploy OND System (Laravel + Livewire) di Jagoan Web VPS dengan Ubuntu 22.04 LTS.

## 📋 Daftar Files

| File | Fungsi | Kapan digunakan |
|------|--------|-----------------|
| `setup.sh` | Setup awal VPS (install semua packages, database, SSL) | 🟢 First time only |
| `setup-osrm.sh` | OSRM self-hosted via Docker (clip radius sekitar depot) | 🟢 Optional, sekali (atau saat area meluas) |
| `deploy.sh` | Update aplikasi dari Git + run migrations | 🔵 Setiap kali deploy |
| `backup.sh` | Backup database + storage files | 🟡 Daily/weekly |
| `monitor.sh` | Monitoring status services & resources | 🟡 Troubleshooting |
| `SETUP-GUIDE.md` | Panduan lengkap step-by-step | 📖 Reference |
| `QUICK-REFERENCE.md` | Command shortcuts & troubleshooting | 📖 Quick lookup |
| `.env.production.example` | Template environment variables | 📋 Configuration |

---

## 🚀 Quick Start

### Step 1: Siapkan VPS di Jagoan Web

- Order VPS paket **Pro** (4GB RAM, 2vCPU, ~Rp 150k/bulan)
- Pilih OS: **Ubuntu 22.04 LTS**
- Request SSH access (root)
- Point domain ke VPS IP

### Step 2: SSH ke VPS

```bash
ssh root@[IP_VPS]
```

### Step 3: Download Scripts

```bash
# Clone repository (recommended)
git clone https://github.com/your-repo/ondsystem.git
cd ondsystem/scripts

# Atau copy manual
# scp setup.sh root@[IP_VPS]:/root/
# scp deploy.sh root@[IP_VPS]:/root/
# scp backup.sh root@[IP_VPS]:/root/
# scp monitor.sh root@[IP_VPS]:/root/
```

### Step 4: Jalankan Setup

```bash
chmod +x *.sh
sudo bash setup.sh
```

**Input yang diperlukan:**
- Domain name
- GitHub repository URL
- Git branch name
- MySQL root password

**Durasi:** 10-15 menit

### Step 5: Verifikasi & Access

```bash
# Check services
bash monitor.sh

# Open in browser
https://yourdomain.com

# Login dengan akun superadmin yang dibuat setup.sh
# (email & password ditampilkan di ringkasan akhir setup.sh — dicatat di sana)
# Superadmin lalu mendaftarkan admin, sales, dan driver lewat aplikasi
```

---

## 📖 Dokumentasi

### Untuk Setup Pertama Kali
Baca: **SETUP-GUIDE.md**
- Prerequisites & persiapan
- Penjelasan step-by-step
- Post-setup configuration
- Troubleshooting lengkap
- Security hardening

### Untuk Daily Operations
Baca: **QUICK-REFERENCE.md**
- Useful commands shortcuts
- Monitoring commands
- Emergency procedures
- Performance tuning
- Common issues

---

## 📝 Script Details

### setup.sh - Initial Server Setup

**Apa yang diinstall:**
```
✅ PHP 8.3 + extensions (pdo_mysql, redis, mbstring, intl, etc)
✅ MySQL 8.0
✅ Redis
✅ Nginx + SSL (Let's Encrypt)
✅ Node.js 18 + npm
✅ Composer
✅ Supervisor (untuk queue worker)
```

**Apa yang di-setup:**
```
✅ Clone repository dari GitHub
✅ Install composer & npm dependencies
✅ Create database & user
✅ Run migrations & seed akun superadmin (tanpa data contoh/dummy)
✅ Generate application key
✅ Configure Nginx + SSL
✅ Setup queue worker (Supervisor)
✅ Setup Laravel scheduler (cron)
✅ Optimize Laravel
```

**Usage:**
```bash
sudo bash setup.sh
```

**Parameters (interaktif):**
- Domain
- App user
- MySQL root password
- GitHub repository URL
- Git branch

---

### deploy.sh - Update & Deployment

**Apa yang dilakukan:**
```
✅ Git pull latest changes
✅ Backup database otomatis
✅ Install/update dependencies (composer, npm)
✅ Build assets (Vite)
✅ Run pending migrations
✅ Clear cache
✅ Optimize
✅ Restart services
```

**Usage:**
```bash
cd /var/www/yourdomain.com
bash deploy.sh
```

**Interaktif:**
- Confirm sebelum deploy
- Ask untuk run migrations

---

### backup.sh - Automated Backup

**Backup apa yang dibuat:**
```
✅ Database (gzip compressed)
✅ Storage/files (tar.gz)
✅ Config (.env, composer.lock, package-lock.json)
```

**Auto-cleanup:** Hapus backup lebih dari 30 hari

**Usage:**
```bash
cd /var/www/yourdomain.com
bash backup.sh
```

**Setup otomatis (crontab):**
```bash
0 2 * * * cd /var/www/yourdomain.com && bash backup.sh
```

---

### monitor.sh - Status & Monitoring

**Cek yang dilakukan:**
```
✅ Service status (nginx, php-fpm, mysql, redis)
✅ Port listening
✅ Process running
✅ Resource usage (memory, disk)
✅ Queue worker status
✅ Recent errors dari log
```

**Usage:**
```bash
bash /var/www/yourdomain.com/monitor.sh
```

---

## 🔧 Configuration

### Environment Variables

Copy `.env.production.example` ke `.env`:

```bash
cp .env.production.example /var/www/yourdomain.com/.env
nano /var/www/yourdomain.com/.env
```

**Key variables to customize:**

```env
APP_URL=https://yourdomain.com

# Database
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password

# Depot location (important for routing)
DEPOT_LAT=-6.175
DEPOT_LNG=106.827
DEPOT_NAMA="Your Warehouse Name"

# Email
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=app-password
```

### Nginx Configuration

Location: `/etc/nginx/sites-available/yourdomain.com`

Pre-configured dengan:
- ✅ HTTPS redirect
- ✅ Gzip compression
- ✅ Security headers
- ✅ Laravel routing
- ✅ PHP-FPM integration

### Supervisor Configuration

Location: `/etc/supervisor/conf.d/ond-queue.conf`

Pre-configured dengan:
- ✅ Queue worker
- ✅ Auto-restart
- ✅ Logging
- ✅ Graceful shutdown

Commands:
```bash
supervisorctl status ond-queue:*
supervisorctl restart ond-queue:*
supervisorctl tail ond-queue:0 -f
```

---

## 📊 System Architecture

```
┌─────────────────────────────────────────────────┐
│          Domain (yourdomain.com)                │
├─────────────────────────────────────────────────┤
│                  Nginx (Port 80/443)            │
│           + SSL (Let's Encrypt)                 │
├─────────────────────────────────────────────────┤
│            PHP 8.3-FPM Workers                  │
│         (5 workers, 512MB memory limit)         │
├─────────────────────────────────────────────────┤
│  Database: MySQL 8.0  |  Cache: Redis           │
│  Session: Redis       |  Queue: Redis           │
└─────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────┐
│         Supervisor: Queue Worker                │
│      php artisan queue:work redis               │
└─────────────────────────────────────────────────┘
```

---

## ✅ Post-Setup Checklist

- [ ] Access aplikasi via HTTPS
- [ ] Login dengan akun superadmin (dari ringkasan setup.sh), lalu ganti password
- [ ] Test fitur utama (pesanan, routing, driver)
- [ ] Queue worker running (`supervisorctl status`)
- [ ] Backup script tested
- [ ] Monitor script working
- [ ] Email configured (if needed)
- [ ] SSL certificate auto-renewal setup
- [ ] Firewall configured
- [ ] Scheduled backup setup (cron)

---

## 🛠️ Common Tasks

### Deploy Aplikasi Baru

```bash
cd /var/www/yourdomain.com
bash deploy.sh
```

### Backup Manual

```bash
cd /var/www/yourdomain.com
bash backup.sh
ls -lh backups/
```

### Check Status

```bash
bash /var/www/yourdomain.com/monitor.sh
```

### View Logs

```bash
# Application
tail -f /var/www/yourdomain.com/storage/logs/laravel.log

# Queue
supervisorctl tail ond-queue:0 -f

# Nginx
tail -f /var/log/nginx/yourdomain.com-error.log
```

### Restart Services

```bash
# Individual
systemctl restart php8.3-fpm
systemctl reload nginx
supervisorctl restart ond-queue:*

# All
systemctl restart php8.3-fpm nginx mysql redis-server && \
supervisorctl restart all
```

### Update Aplikasi

```bash
cd /var/www/yourdomain.com

# Git pull
git pull origin main

# Composer
composer install --no-dev

# NPM
npm install && npm run build

# Database
php artisan migrate --force

# Cache
php artisan cache:clear && php artisan config:cache
```

---

## 🚨 Troubleshooting

### Application Blank / 500 Error

```bash
cd /var/www/yourdomain.com

# Fix permissions
chmod -R 755 .
chmod -R 775 storage bootstrap/cache

# Clear cache
php artisan cache:clear
php artisan config:clear

# Check logs
tail -50 storage/logs/laravel.log
```

### Queue Not Processing

```bash
# Check status
supervisorctl status ond-queue:*

# Restart
supervisorctl restart ond-queue:*

# View logs
supervisorctl tail ond-queue:0 -f

# Check queue count
cd /var/www/yourdomain.com
php artisan queue:monitor
```

### Database Connection Error

```bash
# Check MySQL running
systemctl status mysql

# Test connection
mysql -u ond_app -p -e "SELECT 1;"

# Check .env
grep DB_ /var/www/yourdomain.com/.env
```

### SSL Certificate Issue

```bash
# Check expiry
certbot certificates

# Manual renewal
certbot renew --force-renewal -d yourdomain.com

# Test renewal
certbot renew --dry-run
```

Untuk troubleshooting lebih lengkap, baca **SETUP-GUIDE.md > Troubleshooting**.

---

## 📚 References

- **Laravel Docs**: https://laravel.com/docs
- **Livewire Docs**: https://livewire.laravel.com
- **Supervisor Docs**: http://supervisord.org
- **Nginx Docs**: https://nginx.org/en/docs
- **MySQL Docs**: https://dev.mysql.com/doc
- **Let's Encrypt**: https://letsencrypt.org/docs

---

## 🔐 Security Notes

- ✅ All passwords harus minimal 12 karakter
- ✅ MySQL password auto-generated & disimpan di .env
- ✅ SSH key recommended daripada password
- ✅ Firewall (UFW) setup recommended
- ✅ Regular backups ke external storage recommended
- ✅ SSL auto-renewal via certbot
- ✅ Environment production: APP_DEBUG=false
- ✅ Sensitive files (.env) di-gitignore

---

## 📞 Support

Jika ada issue:

1. Baca **SETUP-GUIDE.md** > Troubleshooting section
2. Check **QUICK-REFERENCE.md** untuk commands
3. Run `bash monitor.sh` untuk status diagnosis
4. Check `/var/www/yourdomain.com/storage/logs/laravel.log`
5. Contact Jagoan Web support jika issue dengan infrastruktur

---

## 🎯 Next Steps Setelah Setup

1. **Customize Environment**
   - Edit `.env` dengan data depot Anda
   - Setup SMTP untuk email
   - Configure OSRM / Nominatim jika diperlukan

2. **Initial Data**
   - Add toko, produk, wilayah via UI
   - Atau import via CSV jika ada data lama

3. **Setup Monitoring**
   - Configure email alerts (optional)
   - Setup log aggregation (Sentry, etc)
   - Monitor queue backlog

4. **Regular Maintenance**
   - Daily: Monitor logs & errors
   - Weekly: Run backups & test restore
   - Monthly: Update OS & packages
   - Quarterly: SSL certificate check

---

**Script Version**: 1.0
**Last Updated**: 2026-08-13
**Compatible**: Ubuntu 22.04 LTS, PHP 8.3, Laravel 13, Livewire 3

---

## 📄 License

Scripts provided as-is for OND System deployment. Modify as needed for your setup.

---

**Happy Deploying! 🚀**
