# OND System - Jagoan Web VPS - Quick Reference

## Directory Shortcuts

```bash
# Main app directory
cd /var/www/yourdomain.com

# Logs
cat storage/logs/laravel.log

# Backups
ls -lh backups/

# Database
mysql -u root -p
mysql -u ond_app -p [database_name]

# Nginx config
/etc/nginx/sites-available/yourdomain.com

# Supervisor config
/etc/supervisor/conf.d/ond-queue.conf

# PHP config
/etc/php/8.3/fpm/pool.d/www.conf
/etc/php/8.3/fpm/php.ini
```

---

## Useful Commands

### Monitoring & Status

```bash
# All services status
systemctl status nginx php8.3-fpm mysql redis-server

# Queue workers
supervisorctl status

# Real-time monitoring
htop
top

# Disk usage
df -h
du -sh /var/www/yourdomain.com

# Network connections
netstat -tuln | grep LISTEN

# Log files (real-time)
tail -f /var/www/yourdomain.com/storage/logs/laravel.log
supervisorctl tail ond-queue:0 -f
tail -f /var/log/nginx/yourdomain.com-error.log
```

### Restart Services

```bash
# Restart individual services
systemctl restart php8.3-fpm
systemctl restart nginx
systemctl restart mysql
systemctl restart redis-server

# Restart queue worker
supervisorctl restart ond-queue:*

# Full system restart (last resort)
systemctl restart php8.3-fpm nginx mysql redis-server
supervisorctl restart all

# Check service enabled on boot
systemctl is-enabled php8.3-fpm nginx mysql redis-server
```

### Laravel Commands (Run as app user)

```bash
cd /var/www/yourdomain.com

# Cache & Config
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Database
php artisan migrate --force

# Seed superadmin saja (aman di produksi, tidak menambah data contoh)
php artisan db:seed --class="Database\Seeders\SuperadminSeeder" --force

# JANGAN jalankan di produksi — DatabaseSeeder penuh berisi data demo/dummy:
# php artisan db:seed --force

# Optimization
php artisan optimize
php artisan optimize:clear

# Storage
php artisan storage:link
php artisan storage:unlink

# Queue troubleshooting
php artisan queue:failed
php artisan queue:retry all
php artisan queue:forget [job_id]

# Tinker (REPL)
php artisan tinker
```

### User & Permissions

```bash
# Change ownership
chown -R ond:ond /var/www/yourdomain.com

# Change permissions
chmod -R 755 /var/www/yourdomain.com
chmod -R 775 /var/www/yourdomain.com/storage
chmod -R 775 /var/www/yourdomain.com/bootstrap/cache

# Check file permissions
ls -la storage/
ls -la bootstrap/cache/

# Run command as specific user
sudo -u ond php artisan command
```

### Database Operations

```bash
# Connect to MySQL
mysql -u ond_app -p

# Inside MySQL:
SHOW DATABASES;
USE ond_database;
SHOW TABLES;
SELECT COUNT(*) FROM pesanans;

# Backup
mysqldump -u ond_app -p ond_database > backup.sql

# Restore
mysql -u ond_app -p ond_database < backup.sql

# Export to CSV
mysql -u ond_app -p ond_database -e \
  "SELECT * FROM pesanans" > pesanans.csv
```

### Git & Deployment

```bash
cd /var/www/yourdomain.com

# Check status
git status
git log --oneline -10

# Pull latest
git pull origin main

# Reset to remote version
git reset --hard origin/main

# Check remotes
git remote -v
git branch -a
```

### SSL Certificate

```bash
# View certificate info
certbot certificates

# Renew all certificates
certbot renew

# Test renewal (dry-run)
certbot renew --dry-run

# Manual renewal for specific domain
certbot certonly --force-renewal -d yourdomain.com
```

### File Operations

```bash
# Search in files
grep -r "search_term" /var/www/yourdomain.com/app

# Find large files
find /var/www/yourdomain.com -type f -size +100M

# Count lines of code
find /var/www/yourdomain.com/app -name "*.php" | wc -l

# View file size
du -sh /var/www/yourdomain.com/storage

# Compress & Archive
tar -czf backup.tar.gz /var/www/yourdomain.com/storage
zip -r backup.zip /var/www/yourdomain.com/storage
```

### System Maintenance

```bash
# Update all packages
apt-get update && apt-get upgrade -y

# Check for updates
apt list --upgradable

# Clean system
apt-get autoremove
apt-get autoclean

# Check system resources
free -h        # Memory
df -h          # Disk
lscpu          # CPU
uptime         # Uptime
```

### Cron Jobs

```bash
# View cron jobs
crontab -l

# Edit cron jobs
crontab -e

# View as specific user
sudo -u ond crontab -l

# Common cron jobs:
# Laravel scheduler
* * * * * cd /var/www/yourdomain.com && php artisan schedule:run

# Backup daily at 2 AM
0 2 * * * cd /var/www/yourdomain.com && bash backup.sh

# Check resources every 5 minutes
*/5 * * * * /usr/local/bin/check-resources.sh
```

### Environment & Configuration

```bash
# View .env
cat /var/www/yourdomain.com/.env

# Edit .env
nano /var/www/yourdomain.com/.env

# Reload config cache
php artisan config:cache

# Check specific env variable
grep APP_DEBUG /var/www/yourdomain.com/.env
```

### Troubleshooting Commands

```bash
# Check if port is in use
lsof -i :80
lsof -i :443
lsof -i :3306

# Kill process
kill -9 [PID]

# Check PHP errors
php -l /var/www/yourdomain.com/app/Models/User.php

# Validate syntax
composer validate

# Check Nginx config
nginx -t

# Check Laravel app
php artisan about
php artisan doctor

# Monitor queue
php artisan queue:monitor

# Test database connection
php artisan tinker
>>> DB::connection()->getPDO()
>>> exit

# Test email
php artisan tinker
>>> Mail::raw('Test', fn($m) => $m->to('test@example.com'))
```

---

## Emergency Procedures

### Out of Memory

```bash
# Increase PHP memory
sed -i 's/memory_limit = 512M/memory_limit = 1024M/' /etc/php/8.3/fpm/php.ini
systemctl restart php8.3-fpm

# Increase MySQL memory
# Edit /etc/mysql/my.cnf
# Increase innodb_buffer_pool_size

# Clear cache to free memory
php artisan cache:clear
redis-cli FLUSHALL
```

### Database Lock

```bash
# Kill stuck process
mysql -u root -p -e "KILL [PROCESS_ID];"

# Show running processes
mysql -u root -p -e "SHOW PROCESSLIST;"

# Kill by user
mysql -u root -p -e "KILL ALL FROM 'ond_app'@'localhost';"
```

### High CPU/Memory

```bash
# Find culprit
top -b -n 1 | head -20

# Check queue backlog
php artisan queue:monitor

# Scale queue workers
# Edit /etc/supervisor/conf.d/ond-queue.conf
# Change numprocs from 1 to 2+
supervisorctl reread && supervisorctl update
```

### Application Unresponsive

```bash
# Check PHP-FPM status
systemctl status php8.3-fpm

# Restart PHP-FPM
systemctl restart php8.3-fpm

# Check Nginx
systemctl status nginx
nginx -t
systemctl restart nginx

# Check logs
tail -100 /var/www/yourdomain.com/storage/logs/laravel.log
```

### SSL Certificate Issues

```bash
# Check expiry
certbot certificates | grep -A 10 yourdomain.com

# Manual renewal
certbot renew --force-renewal -d yourdomain.com

# Regenerate if needed
certbot certonly --force-renewal --nginx -d yourdomain.com
```

---

## Performance Monitoring

```bash
# Real-time monitoring
watch -n 5 'free -h && echo "---" && df -h'

# Get slow query log
tail -100 /var/log/mysql/mysql-slow.log

# Monitor queue
watch 'php artisan queue:monitor'

# Check connection pool
mysql -u root -p -e "SHOW PROCESSLIST;"

# Monitor Redis memory
redis-cli info memory
```

---

## Security Checks

```bash
# Check open ports
sudo ss -tuln
sudo netstat -tuln

# Check listening services
sudo service --status-all

# Check user accounts
cat /etc/passwd

# Check failed SSH attempts
grep "Failed password" /var/log/auth.log | wc -l

# Check sudo history
sudo last | head -20

# Check PHP version
php -v

# Check Nginx version
nginx -v

# Check SSL protocol support
openssl s_client -connect yourdomain.com:443 -tls1_2
```

---

## Useful Aliases (Add to ~/.bashrc)

```bash
alias appdir='cd /var/www/yourdomain.com'
alias logs='tail -f /var/www/yourdomain.com/storage/logs/laravel.log'
alias qlogs='supervisorctl tail ond-queue:0 -f'
alias stat='systemctl status php8.3-fpm nginx mysql redis-server'
alias restart='systemctl restart php8.3-fpm nginx'
alias migrate='php artisan migrate --force'
alias cache-clear='php artisan cache:clear && php artisan config:clear'
alias optimize='php artisan optimize && php artisan optimize:clear'
alias backup='bash backup.sh'
alias monitor='bash monitor.sh'
alias artisan='php artisan'
alias composer='composer'
```

Then reload: `source ~/.bashrc`

---

## Emergency Contacts

**Jagoan Web Support:**
- Website: https://jagoanhosting.com
- Support: support@jagoanhosting.com
- Phone: Check invoice

**Laravel Community:**
- Documentation: https://laravel.com/docs
- Discord: https://discord.gg/laravel
- Stack Overflow: [laravel] tag

---

**Last Updated**: 2026-08-13
