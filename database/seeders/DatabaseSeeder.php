<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Models\PenugasanSales;
use App\Models\Produk;
use App\Models\StokMutasi;
use App\Models\Toko;
use App\Models\User;
use App\Models\Wilayah;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data awal untuk mencoba sistem: pengguna tiap peran, wilayah, produk,
 * dan sebaran toko di Jakarta lengkap dengan koordinatnya.
 *
 * Koordinat dibangkitkan mengelilingi beberapa titik pusat wilayah supaya
 * hasil routing terlihat wajar di peta, bukan tersebar acak.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->pengguna();
        $wilayahs = $this->wilayah();
        $this->produk();
        $this->toko($wilayahs);
        $this->penugasanKunjungan();

        $this->command->newLine();
        $this->command->info('Akun untuk mencoba (kata sandi semuanya: password)');
        $this->command->table(
            ['Peran', 'Email'],
            [
                ['Admin', 'admin@ondsystem.test'],
                ['Sales', 'sales@ondsystem.test'],
                ['Sales', 'sales2@ondsystem.test'],
                ['Driver', 'driver@ondsystem.test'],
                ['Driver', 'driver2@ondsystem.test'],
            ],
        );
    }

    private function pengguna(): void
    {
        $daftar = [
            ['Admin Gudang', 'admin@ondsystem.test', PeranPengguna::Admin],
            ['Sales Lapangan', 'sales@ondsystem.test', PeranPengguna::Sales],
            ['Rina Sales', 'sales2@ondsystem.test', PeranPengguna::Sales],
            ['Budi Driver', 'driver@ondsystem.test', PeranPengguna::Driver],
            ['Andi Driver', 'driver2@ondsystem.test', PeranPengguna::Driver],
        ];

        foreach ($daftar as [$nama, $email, $peran]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $nama,
                'password' => Hash::make('password'),
                'role' => $peran,
                'aktif' => true,
            ]);
        }
    }

    /** @return array<string, array{id: int, nama: string, lat: float, lng: float}> */
    private function wilayah(): array
    {
        $daftar = [
            ['JKT-PST', 'Jakarta Pusat', -6.1751, 106.8272],
            ['JKT-SEL', 'Jakarta Selatan', -6.2615, 106.8106],
            ['JKT-TIM', 'Jakarta Timur', -6.2250, 106.9004],
            ['JKT-BAR', 'Jakarta Barat', -6.1683, 106.7588],
            ['JKT-UTR', 'Jakarta Utara', -6.1214, 106.8745],
        ];

        $hasil = [];

        foreach ($daftar as [$kode, $nama, $lat, $lng]) {
            $wilayah = Wilayah::updateOrCreate(['kode' => $kode], [
                'nama' => $nama,
                'center_lat' => $lat,
                'center_lng' => $lng,
                'aktif' => true,
            ]);

            $hasil[$kode] = ['id' => $wilayah->id, 'nama' => $nama, 'lat' => $lat, 'lng' => $lng];
        }

        return $hasil;
    }

    private function produk(): void
    {
        $daftar = [
            ['PRD-001', 'Air Mineral 600ml', 24_000, 38_000],
            ['PRD-002', 'Air Mineral 1500ml', 18_000, 52_000],
            ['PRD-003', 'Teh Kotak 250ml', 15_000, 46_000],
            ['PRD-004', 'Susu UHT Cokelat 1L', 9_500, 98_000],
            ['PRD-005', 'Kopi Instan Sachet', 12_000, 64_000],
            ['PRD-006', 'Minyak Goreng 2L', 7_200, 145_000],
            ['PRD-007', 'Mie Instan Goreng', 20_000, 112_000],
            ['PRD-008', 'Biskuit Kaleng', 5_400, 165_000],
        ];

        foreach ($daftar as [$kode, $nama, $stok, $harga]) {
            $produk = Produk::updateOrCreate(['kode' => $kode], [
                'nama' => $nama,
                'satuan' => 'dus',
                'stok' => $stok,
                'harga' => $harga,
                'aktif' => true,
            ]);

            if ($produk->mutasis()->doesntExist()) {
                StokMutasi::create([
                    'produk_id' => $produk->id,
                    'tipe' => 'masuk',
                    'jumlah' => $stok,
                    'stok_sesudah' => $stok,
                    'reserved_sesudah' => 0,
                    'keterangan' => 'Stok awal',
                ]);
            }
        }
    }

    /**
     * Membagi toko berfreezer kepada para sales untuk bulan berjalan, sebatas
     * kuota per orang. Satu toko hanya diberikan kepada satu sales.
     */
    private function penugasanKunjungan(): void
    {
        $admin = User::where('email', 'admin@ondsystem.test')->first();
        $salesList = User::sales()->orderBy('id')->get();

        if ($admin === null || $salesList->isEmpty()) {
            return;
        }

        $bulan = CarbonImmutable::today()->startOfMonth()->toDateString();
        $maks = (int) config('visit.maks_toko_per_sales');

        $tokos = Toko::aktif()->berassetId()->orderBy('id')->get();

        // Dibagi rata, bukan mengisi sales pertama sampai kuotanya penuh.
        // Pembagian yang timpang membuat data contoh tidak menggambarkan
        // keadaan sebenarnya, dan sales terakhir bisa kebagian nol toko.
        $perSales = min($maks, (int) ceil($tokos->count() / max(1, $salesList->count())));
        $indeks = 0;

        foreach ($salesList as $sales) {
            $jatah = $tokos->slice($indeks, $perSales);
            $indeks += $jatah->count();

            foreach ($jatah as $toko) {
                PenugasanSales::updateOrCreate(
                    ['toko_id' => $toko->id, 'bulan' => $bulan],
                    ['sales_id' => $sales->id, 'ditugaskan_oleh' => $admin->id],
                );
            }
        }

        $this->command->info("Penugasan kunjungan: {$indeks} toko dibagi ke {$salesList->count()} sales untuk bulan {$bulan}.");
    }

    /** @param  array<string, array{id: int, nama: string, lat: float, lng: float}>  $wilayahs */
    private function toko(array $wilayahs): void
    {
        // Angka acak dibuat bisa diulang agar setiap kali seeder dijalankan
        // sebaran tokonya sama, sehingga hasil percobaan bisa dibandingkan.
        mt_srand(2026);

        $depan = ['Toko', 'Warung', 'Kios', 'Grosir', 'Agen', 'UD', 'Mini Market'];
        $belakang = ['Makmur', 'Jaya', 'Sentosa', 'Berkah', 'Rejeki', 'Bahagia', 'Sejahtera',
            'Mandiri', 'Amanah', 'Barokah', 'Sumber Rezeki', 'Harapan', 'Maju', 'Sinar',
            'Cahaya', 'Bintang', 'Mulia', 'Subur', 'Lestari', 'Abadi'];
        $jalan = ['Merdeka', 'Sudirman', 'Gatot Subroto', 'Diponegoro', 'Ahmad Yani',
            'Pemuda', 'Kartini', 'Cempaka', 'Melati', 'Mawar', 'Anggrek', 'Kenanga'];

        // Sebagian kecil sengaja dibiarkan tanpa koordinat, meniru kondisi
        // data nyata, supaya alur pelengkapan koordinat ikut bisa dicoba.
        $jumlahPerWilayah = ['JKT-PST' => 26, 'JKT-SEL' => 34, 'JKT-TIM' => 22, 'JKT-BAR' => 18, 'JKT-UTR' => 14];

        $nomor = 1;

        foreach ($jumlahPerWilayah as $kode => $jumlah) {
            $w = $wilayahs[$kode];

            for ($i = 0; $i < $jumlah; $i++) {
                $nama = $depan[mt_rand(0, count($depan) - 1)].' '.$belakang[mt_rand(0, count($belakang) - 1)];
                $tanpaTitik = mt_rand(1, 100) <= 8;

                // Nomor aset mengikuti bentuk yang tercetak pada QR freezer.
                // Sebagian kecil dibiarkan kosong, meniru freezer yang belum
                // terpasang, supaya peringatan di layar penugasan ikut teruji.
                $adaFreezer = mt_rand(1, 100) > 6;

                Toko::updateOrCreate(['kode' => sprintf('TK-%04d', $nomor)], [
                    'asset_id' => $adaFreezer ? sprintf('IDNAH2025280%05d', $nomor) : null,
                    'freezer_tipe' => $adaFreezer ? 'SD-280' : null,
                    'freezer_pelanggan' => $adaFreezer ? 'IDN Halocoko' : null,
                    'nama' => $nama.' '.$nomor,
                    'wilayah_id' => $w['id'],
                    'alamat' => 'Jl. '.$jalan[mt_rand(0, count($jalan) - 1)].' No. '.mt_rand(1, 200),
                    'kota' => $w['nama'],
                    'telepon' => '08'.mt_rand(100_000_000, 999_999_999),
                    'latitude' => $tanpaTitik ? null : $w['lat'] + mt_rand(-420, 420) / 10000,
                    'longitude' => $tanpaTitik ? null : $w['lng'] + mt_rand(-420, 420) / 10000,
                    'sumber_koordinat' => $tanpaTitik ? 'belum' : 'manual',
                    'geocoded_at' => $tanpaTitik ? null : now(),
                    'aktif' => true,
                ]);

                $nomor++;
            }
        }
    }
}
