# Pre-Deployment Checklist - OND System Jagoan Web VPS

Checklist untuk memastikan semua sudah siap sebelum menjalankan setup.sh

---

## 🔧 Persiapan Jagoan Web VPS

### Order & Access
- [ ] Order VPS di Jagoan Web dengan spesifikasi:
  - [ ] Paket: **Pro** (4GB RAM, 2vCPU minimum)
  - [ ] OS: **Ubuntu 22.04 LTS**
  - [ ] Storage: 40GB+
  - [ ] Location: Indonesia (untuk latency optimal)

- [ ] Terima invoice & detail akses
- [ ] Catat IP address VPS
- [ ] Test SSH login: `ssh root@[IP_VPS]`
- [ ] Update password root jika diperlukan

### Domain
- [ ] Domain sudah di-register
- [ ] Domain sudah di-pointing ke VPS IP:
  - [ ] Type: A record
  - [ ] Value: [VPS_IP]
- [ ] Verify DNS propagation:
  ```bash
  nslookup yourdomain.com
  # Should show VPS IP
  ```

---

## 📋 GitHub Repository

### Repository Setup
- [ ] GitHub repo sudah public atau private (sesuai kebutuhan)
- [ ] URL repository siap: `https://github.com/username/ondsystem.git`
- [ ] Branch untuk production siap (default: main)

### SSH Keys untuk Automated Deploy
- [ ] (Optional) Generate SSH key untuk VPS:
  ```bash
  ssh-keygen -t ed25519 -f ~/.ssh/jagoanweb_deploy
  ```
- [ ] (Optional) Add public key ke GitHub:
  - [ ] Settings > Deploy keys
  - [ ] Paste public key
  - [ ] Allow write access (if needed for CI/CD)

---

## 📝 Credentials & Passwords

Siapkan & simpan di tempat aman (password manager):

- [ ] **Domain name**: `_______________`
- [ ] **VPS IP**: `_______________`
- [ ] **SSH access**: `root@[IP]`
- [ ] **MySQL Root Password**: `_______________` (min 12 char)
  - Contoh: `Tr0pic@lHvPxR8mN2j9Q`
- [ ] **GitHub Repository URL**: `https://github.com/_______________`
- [ ] **Git Branch**: `_______________` (default: main)
- [ ] **Email Superadmin**: `_______________` (default: superadmin@[domain])
- [ ] **Password Superadmin**: `_______________` (kosongkan saat prompt untuk dibangkitkan otomatis)

### Catat juga:
- [ ] Database name akan jadi: `yourdomain_com` (auto-generated)
- [ ] Database user akan jadi: `ond_app` (auto-generated)
- [ ] Database password: (auto-generated, disimpan di .env)

---

## 📦 Aplikasi OND System

### Repository Content
- [ ] Repo sudah berisi:
  - [ ] `app/` folder (source code)
  - [ ] `database/` folder (migrations & seeders)
  - [ ] `resources/` folder (views & assets)
  - [ ] `routes/` folder
  - [ ] `composer.json`
  - [ ] `package.json`
  - [ ] `.env.example`
  - [ ] `database/seeders/` (berisi seeder data)

### Dependencies
- [ ] `composer.json` berisi:
  - [ ] `laravel/framework: ^13`
  - [ ] `livewire/livewire: ^3`
  - [ ] `laravel/tinker`
  - [ ] Database driver (mysql atau pgsql)

- [ ] `package.json` berisi:
  - [ ] `vite`
  - [ ] `tailwindcss`
  - [ ] Dev dependencies

### Environment
- [ ] `.env.example` sudah ada dan lengkap
  - [ ] DB_CONNECTION=mysql
  - [ ] CACHE_DRIVER, SESSION_DRIVER, QUEUE_CONNECTION
  - [ ] OND System specific vars (DEPOT_*, ROUTING_*, etc)

---

## 💻 Local Development (Optional Verification)

Untuk memastikan aplikasi siap:

- [ ] Clone repo ke local: `git clone [URL]`
- [ ] Setup local:
  ```bash
  composer install
  npm install
  cp .env.example .env
  php artisan key:generate
  # Setup database locally
  php artisan migrate --seed
  ```
- [ ] Test aplikasi lokal: `composer dev`
- [ ] Verify fitur utama berfungsi
- [ ] Check tidak ada error di console

---

## 🔐 Security Preparations

### SSL Certificate
- [ ] Domain siap untuk Let's Encrypt (Let's Encrypt akan auto-setup)
- [ ] Tidak menggunakan wildcard cert (tidak perlu)

### Firewall
- [ ] Firewall Jagoan Web:
  - [ ] Allow port 22 (SSH)
  - [ ] Allow port 80 (HTTP)
  - [ ] Allow port 443 (HTTPS)
  - [ ] (Optional) Restrict SSH ke IP specific

### SSH Security
- [ ] Root password di-change dari default
- [ ] (Optional) Setup SSH key untuk password-less login
- [ ] (Optional) Disable root login setelah setup

### Backup
- [ ] Backup destination siap (local/cloud):
  - [ ] AWS S3 (optional)
  - [ ] Google Drive (optional)
  - [ ] Local storage di VPS (included)

---

## 📞 Contact & Support

### Jagoan Web
- [ ] Catat support contact:
  - [ ] Nomor ticket support
  - [ ] Email support
  - [ ] WhatsApp/phone number

### Emergency Contacts
- [ ] Emergency contact jika server down
- [ ] Backup person yang bisa SSH ke VPS

---

## 🚀 Pre-Setup Commands

Jalankan ini dari local terminal untuk prepare:

```bash
# Test domain DNS
nslookup yourdomain.com

# Test SSH access
ssh root@[VPS_IP]
# Jika berhasil, ketik 'exit'

# Test GitHub access (optional)
git clone https://github.com/username/ondsystem.git test-repo
rm -rf test-repo
```

---

## 📥 Download Scripts

### Option A: Clone dari GitHub
```bash
# Di local machine
git clone https://github.com/your-repo/ondsystem.git
cd ondsystem/scripts
# Scripts akan ada di folder ini
```

### Option B: Download via SCP
```bash
# Siapkan files di local, copy ke VPS
cd /path/to/scripts
scp setup.sh deploy.sh backup.sh monitor.sh root@[VPS_IP]:/root/
```

### Option C: Manual Copy
```bash
# SSH ke VPS
ssh root@[VPS_IP]

# Buat folder scripts
mkdir -p /root/scripts
cd /root/scripts

# Paste content dari setiap file
nano setup.sh
# (Paste content, Ctrl+D to save)

nano deploy.sh
# (Paste content, Ctrl+D to save)

# etc...
chmod +x *.sh
```

---

## ✅ Final Verification (Hari Setup)

Sebelum jalankan `setup.sh`:

```bash
# Login ke VPS
ssh root@[VPS_IP]

# Verify OS
cat /etc/os-release
# Should show: Ubuntu 22.04

# Check internet connection
ping google.com

# Check disk space
df -h
# Should have at least 20GB available

# Check RAM
free -h
# Should have at least 4GB

# Check if scripts are executable
ls -la setup.sh deploy.sh backup.sh monitor.sh
# Should show -rwxr-xr-x (executable)

# Ready to deploy!
```

---

## 🎯 Setup Execution Checklist

Saat akan jalankan `setup.sh`:

```bash
# 1. SSH ke VPS
ssh root@[VPS_IP]

# 2. Masuk ke folder scripts
cd /root/scripts  # atau di mana pun file-nya

# 3. Jalankan setup (akan prompt untuk input)
sudo bash setup.sh

# 4. Input yang akan diminta:
#    - Domain name
#    - App username (default: ond)
#    - MySQL root password
#    - GitHub repo URL
#    - Git branch (default: main)

# 5. Tunggu hingga selesai (~10-15 menit)

# 6. Catat output (terutama password-password)

# 7. Verify setup dengan monitor script
bash monitor.sh
```

---

## 📋 Input Data untuk Setup Script

Siapkan data ini sebelum jalankan setup.sh:

```
Domain:                   yourdomain.com
App Username:             ond
MySQL Root Password:      Tr0pic@lHvPxR8mN2j9Q
GitHub Repository:        https://github.com/user/ondsystem.git
Git Branch:               main
```

---

## ⚠️ Common Issues & Prevention

### Issue: Domain DNS tidak resolve
**Prevention:**
- [ ] Verify domain di Jagoan Web DNS management
- [ ] Wait 24 jam untuk propagasi
- [ ] Test dengan: `nslookup yourdomain.com`

### Issue: SSH key fingerprint warning
**Prevention:**
- [ ] First login SSH akan ask to confirm fingerprint
- [ ] Type `yes` untuk proceed
- [ ] Ini normal (ECDSA host key)

### Issue: Permission denied pada scripts
**Prevention:**
- [ ] Make scripts executable: `chmod +x setup.sh`
- [ ] Run dengan sudo: `sudo bash setup.sh`

### Issue: Database password contains special characters
**Prevention:**
- [ ] Gunakan password yang simple saat setup
- [ ] Escaping sudah handled oleh script

---

## 🔄 Post-Setup Follow-up

Setelah setup.sh selesai:

- [ ] Access aplikasi: `https://yourdomain.com`
- [ ] Verify SSL certificate valid
- [ ] Login dengan akun superadmin (email/password dari ringkasan setup.sh)
- [ ] Test main features (pesanan, routing)
- [ ] Check queue worker running
- [ ] Setup automated backup (cron)
- [ ] Customize .env (DEPOT, email, etc)
- [ ] Test deploy.sh untuk update
- [ ] Setup monitoring alerts (optional)
- [ ] Document credentials di safe place

---

## 📞 Siap Untuk Setup?

Jika semua checklist sudah ✓, Anda siap menjalankan:

```bash
ssh root@[VPS_IP]
cd /root/scripts
sudo bash setup.sh
```

**Good luck! 🚀**

---

**Created**: 2026-08-13
**For**: OND System v1.0 on Ubuntu 22.04 LTS
