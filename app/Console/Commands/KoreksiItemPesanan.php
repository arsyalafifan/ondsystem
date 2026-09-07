<?php

namespace App\Console\Commands;

use App\Enums\PeranPengguna;
use App\Models\Depot;
use App\Models\PesananItem;
use App\Models\User;
use App\Services\PengirimanService;
use App\Support\DepotContext;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Solusi sementara untuk kasus driver salah unggah nota: toko sebenarnya
 * tidak mengambil semua barang, tapi driver mengunggah nota lewat jalur
 * pengiriman penuh (bukan coret nota) sehingga pesanan tercatat SELESAI
 * dengan seluruh isinya dianggap terkirim.
 *
 * Dipakai lewat SSH ke server, bukan lewat layar admin — dijalankan sekali
 * per baris yang keliru, dengan konfirmasi sebelum menyimpan apa pun.
 */
class KoreksiItemPesanan extends Command
{
    protected $signature = 'pesanan:koreksi-item
        {kode_pesanan : Kode pesanan, mis. PSN-20260821-0001}
        {kode_produk : Kode produk pada baris yang mau dikoreksi}
        {jumlah_diterima : Jumlah dus yang BENAR-BENAR diterima toko}
        {--depot= : Kode depot tempat pesanan ini berada}
        {--admin= : Email admin yang melakukan koreksi (bawaan: admin pertama)}
        {--catatan= : Catatan opsional untuk jejak mutasi stok}';

    protected $description = 'Mengoreksi jumlah diterima pada pesanan yang sudah terlanjur SELESAI padahal tokonya tidak mengambil semua barang';

    public function handle(PengirimanService $service): int
    {
        $depot = Depot::query()->where('kode', $this->option('depot'))->first();

        if ($depot === null) {
            $this->error('Depot tidak ditemukan. Isi --depot=KODE_DEPOT (lihat tabel depots).');

            return self::FAILURE;
        }

        return DepotContext::jalankanSebagai($depot, fn () => $this->koreksi($service));
    }

    private function koreksi(PengirimanService $service): int
    {
        $kodePesanan = (string) $this->argument('kode_pesanan');
        $kodeProduk = (string) $this->argument('kode_produk');
        $jumlah = (int) $this->argument('jumlah_diterima');

        $item = PesananItem::query()
            ->whereHas('pesanan', fn ($q) => $q->where('kode', $kodePesanan))
            ->whereHas('produk', fn ($q) => $q->where('kode', $kodeProduk))
            ->with(['pesanan', 'produk'])
            ->first();

        if ($item === null) {
            $this->error("Baris pesanan {$kodePesanan} / produk {$kodeProduk} tidak ditemukan.");

            return self::FAILURE;
        }

        $admin = $this->option('admin')
            ? User::where('email', $this->option('admin'))->first()
            : User::where('role', PeranPengguna::Admin)->first();

        if ($admin === null) {
            $this->error('Akun admin untuk jejak koreksi tidak ditemukan. Isi --admin=email@contoh.com.');

            return self::FAILURE;
        }

        $this->table(['Field', 'Nilai'], [
            ['Pesanan', $item->pesanan->kode],
            ['Produk', $item->produk->nama],
            ['Dipesan', $item->jumlah_dus],
            ['Tercatat terkirim (sekarang)', $item->terkirim],
            ['Dikoreksi jadi', $jumlah],
            ['Stok yang akan dikembalikan', max(0, $item->terkirim - $jumlah)],
            ['Dilakukan sebagai', "{$admin->name} ({$admin->email})"],
        ]);

        if (! $this->confirm('Lanjutkan koreksi ini?')) {
            $this->warn('Dibatalkan, tidak ada yang diubah.');

            return self::SUCCESS;
        }

        try {
            $service->koreksiItemSetelahSelesai($item, $jumlah, $admin, $this->option('catatan'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Koreksi berhasil disimpan. Cek tabel stok_mutasis untuk jejaknya.');

        return self::SUCCESS;
    }
}
