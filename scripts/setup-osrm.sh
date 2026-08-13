#!/bin/bash

###############################################################################
# OND System - Self-Hosted OSRM (Docker)
#
# Menjalankan OSRM sendiri lewat Docker, dipotong (clip) ke radius tertentu
# di sekitar depot — bukan seluruh Indonesia. Geofabrik hanya menyediakan
# ekstrak per-pulau untuk Indonesia (bukan per-provinsi), jadi cara paling
# dekat ke "1 provinsi" adalah: download ekstrak 1 pulau, lalu potong dengan
# radius (km) di sekitar koordinat depot. Radius ~150km kira-kira setara
# luas satu provinsi di Jawa.
#
# Hasilnya OSRM_URL utama (self-hosted, http://127.0.0.1:5000) dipasang di
# .env, dengan OSRM_FALLBACK_URL tetap mengarah ke server publik sebagai
# jaring pengaman kedua kalau container ini down.
#
# Usage: sudo bash setup-osrm.sh
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
    log_error "Jalankan sebagai root: sudo bash setup-osrm.sh"
    exit 1
fi

###############################################################################
# Input
###############################################################################
read -p "Path aplikasi (untuk baca DEPOT_LAT/DEPOT_LNG dari .env, contoh: /var/www/yourdomain.com): " APP_PATH

if [ ! -f "$APP_PATH/.env" ]; then
    log_error ".env tidak ditemukan di $APP_PATH"
    exit 1
fi

DEPOT_LAT=$(grep "^DEPOT_LAT=" "$APP_PATH/.env" | cut -d '=' -f2)
DEPOT_LNG=$(grep "^DEPOT_LNG=" "$APP_PATH/.env" | cut -d '=' -f2)

if [ -z "$DEPOT_LAT" ] || [ -z "$DEPOT_LNG" ]; then
    log_error "DEPOT_LAT / DEPOT_LNG kosong di .env"
    exit 1
fi

log_info "Depot: $DEPOT_LAT, $DEPOT_LNG"

echo ""
echo "Pilih pulau sumber data (Geofabrik hanya menyediakan per-pulau untuk Indonesia):"
echo "  1) Jawa          (~250-400MB)  - depot di Jakarta/Jawa pakai ini"
echo "  2) Sumatra       (~400-600MB)"
echo "  3) Kalimantan    (~200-300MB)"
echo "  4) Sulawesi      (~150-250MB)"
echo "  5) Bali & Nusa Tenggara (~80-150MB)"
echo "  6) Maluku & Papua (~150-250MB)"
echo "  7) Masukkan URL .osm.pbf sendiri"
read -p "Pilihan [1-7] (default 1): " PULAU_PILIHAN
PULAU_PILIHAN=${PULAU_PILIHAN:-1}

GEOFABRIK_BASE="https://download.geofabrik.de/asia/indonesia"
case $PULAU_PILIHAN in
    1) SOURCE_URL="$GEOFABRIK_BASE/java-latest.osm.pbf" ;;
    2) SOURCE_URL="$GEOFABRIK_BASE/sumatra-latest.osm.pbf" ;;
    3) SOURCE_URL="$GEOFABRIK_BASE/kalimantan-latest.osm.pbf" ;;
    4) SOURCE_URL="$GEOFABRIK_BASE/sulawesi-latest.osm.pbf" ;;
    5) SOURCE_URL="$GEOFABRIK_BASE/nusa-tenggara-latest.osm.pbf" ;;
    6) SOURCE_URL="$GEOFABRIK_BASE/maluku-papua-latest.osm.pbf" ;;
    7) read -p "URL .osm.pbf: " SOURCE_URL ;;
    *) log_error "Pilihan tidak valid"; exit 1 ;;
esac

log_warn "Nama file Geofabrik bisa berubah sewaktu-waktu. Kalau download gagal di"
log_warn "bawah, cek nama pasti di https://download.geofabrik.de/asia/indonesia.html"
log_warn "lalu jalankan ulang script ini dengan pilihan 7 (URL manual)."

read -p "Radius clip di sekitar depot, dalam km (default 150, ~seukuran 1 provinsi): " RADIUS_KM
RADIUS_KM=${RADIUS_KM:-150}

echo ""
log_info "Ringkasan:"
log_info "  Sumber: $SOURCE_URL"
log_info "  Depot: $DEPOT_LAT, $DEPOT_LNG"
log_info "  Radius clip: ${RADIUS_KM}km"
echo ""
read -p "Lanjutkan? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_error "Dibatalkan"
    exit 1
fi

###############################################################################
# 1. Install Docker & osmium-tool
###############################################################################
log_info "=== 1. Install Docker & osmium-tool ==="

if ! command -v docker &> /dev/null; then
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
    log_success "Docker installed"
else
    log_info "Docker sudah terpasang"
fi

apt-get update
apt-get install -y osmium-tool bc

log_success "Dependencies installed"

###############################################################################
# 2. Download & Clip Data
###############################################################################
log_info "=== 2. Download Data OSM ==="

DATA_DIR="/opt/ond-osrm"
mkdir -p $DATA_DIR
cd $DATA_DIR

if [ ! -f source.osm.pbf ]; then
    if ! wget -O source.osm.pbf "$SOURCE_URL"; then
        log_error "Download gagal. Cek URL di https://download.geofabrik.de/asia/indonesia.html"
        rm -f source.osm.pbf
        exit 1
    fi
else
    log_info "source.osm.pbf sudah ada, skip download (hapus file ini untuk paksa download ulang)"
fi

SOURCE_SIZE=$(du -h source.osm.pbf | cut -f1)
log_success "Data terdownload: $SOURCE_SIZE"

log_info "=== 3. Potong ke Radius ${RADIUS_KM}km dari Depot ==="

# 1 derajat lintang ~ 111.32 km. Derajat bujur menyempit sesuai cosinus lintang.
DLAT=$(echo "scale=6; $RADIUS_KM / 111.32" | bc)
DLNG=$(echo "scale=6; $RADIUS_KM / (111.32 * c($DEPOT_LAT * 3.14159265 / 180))" | bc -l)
DLNG=${DLNG#-}  # absolut

LEFT=$(echo "scale=6; $DEPOT_LNG - $DLNG" | bc)
RIGHT=$(echo "scale=6; $DEPOT_LNG + $DLNG" | bc)
BOTTOM=$(echo "scale=6; $DEPOT_LAT - $DLAT" | bc)
TOP=$(echo "scale=6; $DEPOT_LAT + $DLAT" | bc)

log_info "Bounding box: [$LEFT, $BOTTOM, $RIGHT, $TOP]"

osmium extract -b "$LEFT,$BOTTOM,$RIGHT,$TOP" source.osm.pbf -o clipped.osm.pbf --overwrite

CLIPPED_SIZE=$(du -h clipped.osm.pbf | cut -f1)
log_success "Data terpotong: $CLIPPED_SIZE (dari $SOURCE_SIZE)"

###############################################################################
# 4. Build Routing Graph (osrm-extract, partition, customize)
###############################################################################
log_info "=== 4. Proses Data untuk Routing (bisa beberapa menit) ==="

OSRM_IMAGE="ghcr.io/project-osrm/osrm-backend"

docker run --rm -t -v "$DATA_DIR:/data" $OSRM_IMAGE \
    osrm-extract -p /opt/car.lua /data/clipped.osm.pbf

docker run --rm -t -v "$DATA_DIR:/data" $OSRM_IMAGE \
    osrm-partition /data/clipped.osrm

docker run --rm -t -v "$DATA_DIR:/data" $OSRM_IMAGE \
    osrm-customize /data/clipped.osrm

log_success "Data routing siap"

###############################################################################
# 5. Jalankan OSRM Container (persistent)
###############################################################################
log_info "=== 5. Jalankan OSRM Server ==="

docker rm -f ond-osrm 2>/dev/null || true

# Bind ke 127.0.0.1 saja — hanya aplikasi di server ini yang perlu akses,
# tidak perlu (dan tidak aman) diekspos ke internet.
docker run -d \
    --name ond-osrm \
    --restart unless-stopped \
    -t \
    -v "$DATA_DIR:/data" \
    -p 127.0.0.1:5000:5000 \
    $OSRM_IMAGE \
    osrm-routed --algorithm mld --max-table-size 1000 /data/clipped.osrm

log_info "Menunggu container siap..."
sleep 5

###############################################################################
# 6. Health Check
###############################################################################
log_info "=== 6. Verifikasi ==="

TEST_URL="http://127.0.0.1:5000/route/v1/driving/${DEPOT_LNG},${DEPOT_LAT};$(echo "$DEPOT_LNG + 0.01" | bc),$(echo "$DEPOT_LAT + 0.01" | bc)"

if curl -s -f "$TEST_URL" | grep -q '"code":"Ok"'; then
    log_success "OSRM merespons dengan benar"
else
    log_warn "OSRM belum merespons benar, cek log: docker logs ond-osrm"
fi

###############################################################################
# Summary
###############################################################################
echo ""
echo "================================================================================"
echo -e "${GREEN}✓ OSRM SELF-HOSTED SIAP${NC}"
echo "================================================================================"
echo ""
echo "Container: ond-osrm (restart otomatis kalau server reboot / crash)"
echo "Endpoint: http://127.0.0.1:5000"
echo "Data: $DATA_DIR ($CLIPPED_SIZE, radius ${RADIUS_KM}km dari depot)"
echo ""
echo "Langkah selanjutnya — update $APP_PATH/.env:"
echo ""
echo "  OSRM_URL=http://127.0.0.1:5000"
echo "  OSRM_FALLBACK_URL=https://router.project-osrm.org"
echo "  OSRM_ENABLED=true"
echo ""
echo "Lalu:"
echo "  cd $APP_PATH"
echo "  php artisan config:cache"
echo "  systemctl restart php8.3-fpm"
echo ""
echo "Perilaku sekarang: OSRM lokal dicoba dulu, kalau container ini down mesin"
echo "routing otomatis pindah ke server publik, dan kalau itu juga gagal, jatuh"
echo "ke perhitungan garis lurus. Aplikasi tidak pernah berhenti karena ini."
echo ""
echo "Monitoring:"
echo "  docker ps --filter name=ond-osrm"
echo "  docker logs -f ond-osrm"
echo "  docker stats ond-osrm --no-stream"
echo ""
echo "Kalau nanti butuh area lebih luas, jalankan ulang script ini dengan pulau"
echo "atau radius yang lebih besar — data lama akan ditimpa."
echo ""
echo "================================================================================"
