<?php

namespace App\Livewire\Insentif;

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Berapa dus yang berhasil terantar per akun sales (yang menginput
 * pesanannya) — dasar hitung insentif. "Insentif Driver" menyusul lewat
 * layar serupa nanti, dihitung dari kunjungan yang diselesaikan driver di
 * lapangan, bukan dari siapa yang menginput pesanan.
 *
 * Dus yang dihitung adalah yang BENAR-BENAR terkirim
 * (`PesananItem::terkirim`, yang sudah memperhitungkan koreksi nota
 * dicoret), bukan jumlah pesanan mentah — toko yang cuma mengambil
 * sebagian tidak boleh dihitung penuh sebagai insentif.
 *
 * Hanya pesanan yang penginputnya (`dibuat_oleh`) berperan SALES yang
 * dihitung: pesanan kampas dibuat driver (bukan sales, jadi otomatis tidak
 * ikut lewat penyaring peran ini — tidak perlu mengecualikan jenisnya
 * secara eksplisit), dan penjualan POS yang diinput ADMIN sengaja tidak
 * dihitung sebagai insentif sales — hanya POS yang diinput sales sendiri
 * yang ikut terhitung.
 */
class InsentifSales extends Component
{
    #[Url(as: 'mode')]
    public string $mode = 'bulan';

    #[Url(as: 'tgl')]
    public string $tanggal = '';

    #[Url(as: 'bln')]
    public string $bulan = '';

    #[Url(as: 'dari')]
    public string $dariTanggal = '';

    #[Url(as: 'sampai')]
    public string $sampaiTanggal = '';

    public function mount(): void
    {
        $this->tanggal = $this->tanggal ?: today()->toDateString();
        $this->bulan = $this->bulan ?: today()->format('Y-m');
        $this->dariTanggal = $this->dariTanggal ?: today()->subDays(6)->toDateString();
        $this->sampaiTanggal = $this->sampaiTanggal ?: today()->toDateString();
    }

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['mode', 'tanggal', 'bulan', 'dariTanggal', 'sampaiTanggal'], true)) {
            unset($this->pesanans, $this->perSales, $this->dataChart, $this->totalDusKeseluruhan);
            $this->dispatch('insentif-diperbarui', data: $this->dataChart);
        }
    }

    /**
     * Selesai_at dipilih sebagai patokan tanggal (bukan `tanggal`, yang
     * cuma target awal, atau `created_at`, yang cuma waktu diinput) —
     * konsisten dengan Pelunasan/Pendapatan yang juga memakai kapan pesanan
     * SUNGGUH tuntas sebagai patokan "kapan"-nya. Insentif dihitung atas
     * dus yang benar-benar sudah keluar, bukan yang baru dipesan.
     *
     * @return Collection<int, Pesanan>
     */
    #[Computed]
    public function pesanans(): Collection
    {
        return Pesanan::query()
            ->where('status', StatusPesanan::Selesai)
            ->whereHas('pembuat', fn ($q) => $q->where('role', PeranPengguna::Sales))
            ->with(['items', 'pembuat:id,name'])
            ->when($this->mode === 'hari', fn ($q) => $q->whereDate('selesai_at', $this->tanggal))
            ->when($this->mode === 'bulan', function ($q) {
                $bulan = CarbonImmutable::parse($this->bulan.'-01');
                $q->whereBetween('selesai_at', [$bulan->startOfMonth(), $bulan->endOfMonth()]);
            })
            ->when($this->mode === 'rentang', fn ($q) => $q->whereBetween('selesai_at', [
                CarbonImmutable::parse($this->dariTanggal)->startOfDay(),
                CarbonImmutable::parse($this->sampaiTanggal)->endOfDay(),
            ]))
            // mode 'semua': tidak ada penyaring tanggal sama sekali.
            ->get();
    }

    /**
     * Rekap per sales, terurut dus terbanyak dulu — supaya langsung
     * terlihat siapa yang paling banyak menyumbang tanpa perlu diurutkan
     * manual dari tabel.
     *
     * @return Collection<int, array{user_id: int, nama: string, total_dus: int, total_pesanan: int, total_toko: int}>
     */
    #[Computed]
    public function perSales(): Collection
    {
        return $this->pesanans
            ->groupBy('dibuat_oleh')
            ->map(function (Collection $grup) {
                $pembuat = $grup->first()->pembuat;

                return [
                    'user_id' => $pembuat->id,
                    'nama' => $pembuat->name,
                    // is_bonus dikecualikan sebagai pertahanan berlapis: secara
                    // struktural item bonus tidak seharusnya pernah muncul di
                    // sini sama sekali (cuma admin/superadmin yang bisa
                    // menginputnya, dan whereHas('pembuat', role Sales) di atas
                    // sudah menyaring pesanan yang dibuat admin), tapi baris ini
                    // memastikan dus bonus TETAP tidak ikut terhitung sekalipun
                    // asumsi itu suatu saat berubah.
                    'total_dus' => (int) $grup->sum(
                        fn (Pesanan $p) => $p->items->where('is_bonus', false)->sum->terkirim
                    ),
                    'total_pesanan' => $grup->count(),
                    'total_toko' => $grup->pluck('toko_id')->unique()->count(),
                ];
            })
            ->sortByDesc('total_dus')
            ->values();
    }

    #[Computed]
    public function totalDusKeseluruhan(): int
    {
        return (int) $this->perSales->sum('total_dus');
    }

    #[Computed]
    public function dataChart(): array
    {
        return [
            'labels' => $this->perSales->pluck('nama')->all(),
            'data' => $this->perSales->pluck('total_dus')->all(),
        ];
    }

    public function render()
    {
        return view('livewire.insentif.insentif-sales')->title(__('insentif.judul_sales'));
    }
}
