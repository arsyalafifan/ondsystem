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
 *
 * Dus BONUS dihitung beda-beda menurut asalnya (lihat perSales()): bonus
 * MANUAL (admin/superadmin, lihat "Bonus produk" di Input Pesanan/POS)
 * dikecualikan — itu pemberian sepihak admin, bukan hasil jualan sales.
 * Bonus PROMO (`Promo::aktifPada()`, lihat [Promo] di README) justru ikut
 * dihitung penuh — itu jatah yang sales benar-benar peroleh dari
 * pencapaian jualan mereka sendiri (15 dus terjual → dapat 1 dus bonus,
 * keduanya sama-sama usaha sales), bukan pemberian sepihak siapa pun.
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
     * `Pesanan::tanggalPendapatanAntara()` dipakai sebagai patokan tanggal —
     * scope BERSAMA dengan menu Pendapatan (lihat dokumentasinya di
     * `Pesanan.php`), supaya kedua layar SELALU konsisten: tanggal
     * keberangkatan kendaraan untuk pesanan yang lewat rute (rute biasa
     * maupun kampas), tanggal_lunas untuk POS (yang tidak pernah lewat
     * kendaraan). BUKAN `tanggal` (cuma target awal), `created_at` (cuma
     * waktu diinput), atau `selesai_at` (kapan toko benar-benar menerima —
     * bisa menyusul beberapa hari dari keberangkatan, sama seperti
     * tanggal_lunas yang dulu dipakai di sini).
     *
     * @return Collection<int, Pesanan>
     */
    #[Computed]
    public function pesanans(): Collection
    {
        return Pesanan::query()
            ->where('status', StatusPesanan::Selesai)
            ->whereHas('pembuat', fn ($q) => $q->where('role', PeranPengguna::Sales))
            ->with(['items', 'pembuat:id,name', 'stop.kendaraan'])
            ->when($this->mode === 'hari', fn ($q) => $q->tanggalPendapatanAntara($this->tanggal, $this->tanggal))
            ->when($this->mode === 'bulan', function ($q) {
                $bulan = CarbonImmutable::parse($this->bulan.'-01');
                $q->tanggalPendapatanAntara($bulan->startOfMonth()->toDateString(), $bulan->endOfMonth()->toDateString());
            })
            ->when($this->mode === 'rentang', fn ($q) => $q->tanggalPendapatanAntara($this->dariTanggal, $this->sampaiTanggal))
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
                    // Item bonus MANUAL tetap dikecualikan (secara struktural
                    // tidak pernah muncul di sini sama sekali — cuma
                    // admin/superadmin yang bisa menginputnya, dan
                    // whereHas('pembuat', role Sales) di atas sudah menyaring
                    // pesanan yang dibuat admin — baris ini cuma pertahanan
                    // berlapis, jaga-jaga kalau asumsi itu berubah). Item
                    // bonus PROMO (promo_id terisi) justru IKUT dihitung: itu
                    // jatah yang sales peroleh dari pencapaian jualan mereka
                    // sendiri, bukan pemberian sepihak seperti bonus manual —
                    // dibedakan lewat promo_id pesanan, bukan is_bonus item
                    // saja, karena satu pesanan dengan promo bisa punya baris
                    // biasa DAN baris bonus yang sama-sama harus terhitung.
                    'total_dus' => (int) $grup->sum(function (Pesanan $p) {
                        return $p->items
                            ->filter(fn ($item) => ! $item->is_bonus || $p->promo_id !== null)
                            ->sum->terkirim;
                    }),
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
