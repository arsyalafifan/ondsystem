<?php

namespace App\Livewire\Pembayaran;

use App\Enums\JenisPesanan;
use App\Enums\StatusBayar;
use App\Models\Pesanan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Pendapatan extends Component
{
    #[Url(as: 'mode')]
    public string $mode = 'hari';

    #[Url(as: 'tgl')]
    public string $tanggal = '';

    #[Url(as: 'bln')]
    public string $bulan = '';

    #[Url(as: 'dari')]
    public string $dariTanggal = '';

    #[Url(as: 'sampai')]
    public string $sampaiTanggal = '';

    // --- Penyaring tabel riwayat — hanya menyaring tabel per pesanan di
    // bawah, bukan kartu ringkasan/kategori/grafik di atasnya. Kartu-kartu
    // itu tetap menunjukkan gambaran keseluruhan rentang tanggal; tabel
    // riwayat adalah alat cari yang menyaring DI DALAM rentang itu. ---

    #[Url(as: 'q')]
    public string $riwayatCari = '';

    #[Url(as: 'kat')]
    public string $riwayatKategori = '';

    #[Url(as: 'metode')]
    public string $riwayatMetode = '';

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
            unset(
                $this->pesanans, $this->ringkasanHarian, $this->dataChart,
                $this->totalKeseluruhan, $this->totalCash, $this->totalTransfer,
                $this->totalPerKategori, $this->riwayat,
            );
            $this->dispatch('pendapatan-diperbarui', data: $this->dataChart);
        } elseif (in_array($kolom, ['riwayatCari', 'riwayatKategori', 'riwayatMetode'], true)) {
            unset($this->riwayat);
        }
    }

    public function bersihkanFilterRiwayat(): void
    {
        $this->reset(['riwayatCari', 'riwayatKategori', 'riwayatMetode']);
        unset($this->riwayat);
    }

    #[Computed]
    public function pesanans(): Collection
    {
        return Pesanan::query()
            ->where('status_bayar', StatusBayar::Lunas)
            ->with(['items', 'toko:id,nama', 'stop.kendaraan.batch'])
            ->when($this->mode === 'hari', fn ($q) => $this->saringTanggalPendapatan($q, $this->tanggal, $this->tanggal))
            ->when($this->mode === 'bulan', function ($q) {
                $bulan = CarbonImmutable::parse($this->bulan.'-01');
                $this->saringTanggalPendapatan($q, $bulan->startOfMonth()->toDateString(), $bulan->endOfMonth()->toDateString());
            })
            ->when($this->mode === 'rentang', fn ($q) => $this->saringTanggalPendapatan($q, $this->dariTanggal, $this->sampaiTanggal))
            ->get();
    }

    /**
     * Menyaring berdasarkan `Pesanan::tanggal_pendapatan` (lihat dokumentasi
     * accessor-nya) LANGSUNG lewat kueri — bukan disaring belakangan di
     * memori, supaya penyaring "hari/bulan/rentang" tetap bekerja di
     * lapisan basis data seperti sebelumnya. Kategori driver (rute biasa +
     * kampas) disaring lewat tanggal keberangkatan kendaraannya
     * (`RoutingBatch::tanggal`); kategori pos (tidak pernah lewat
     * kendaraan) tetap lewat tanggal_lunas.
     *
     * Dua batas dibandingkan lewat whereDate(), BUKAN whereBetween() dengan
     * string tanggal polos — kolom `date` di Eloquent bisa saja tersimpan
     * dengan sisa waktu "00:00:00" di baliknya (tergantung driver basis
     * data), sehingga whereBetween(['2026-08-20', '2026-08-20']) gagal
     * mencocokkan nilai '2026-08-20 00:00:00' (secara leksikografis nilai
     * itu dianggap LEBIH BESAR dari batas atasnya). whereDate() mengekstrak
     * bagian tanggalnya lewat SQL sebelum dibandingkan, jadi kebal dari
     * sisa waktu semacam itu di kedua sisi.
     */
    private function saringTanggalPendapatan(Builder $query, string $dari, string $sampai): void
    {
        $query->where(function (Builder $q) use ($dari, $sampai) {
            $q->where(function (Builder $qq) use ($dari, $sampai) {
                $qq->whereIn('jenis', [JenisPesanan::Normal->value, JenisPesanan::Kampas->value])
                    ->whereHas('stop.kendaraan.batch', function (Builder $b) use ($dari, $sampai) {
                        $b->whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai);
                    });
            })->orWhere(function (Builder $qq) use ($dari, $sampai) {
                $qq->where('jenis', JenisPesanan::Pos->value)
                    ->whereDate('tanggal_lunas', '>=', $dari)
                    ->whereDate('tanggal_lunas', '<=', $sampai);
            });
        });
    }

    #[Computed]
    public function ringkasanHarian(): Collection
    {
        return $this->pesanans
            ->groupBy(fn (Pesanan $p) => $p->tanggal_pendapatan->toDateString())
            ->map(fn (Collection $grup) => $grup->sum->tagihan)
            ->sortKeys();
    }

    #[Computed]
    public function totalKeseluruhan(): float
    {
        return $this->ringkasanHarian->sum();
    }

    /**
     * Pendapatan menurut sumbernya. `nominal_cash`/`nominal_transfer`
     * dicatat manual saat admin menandai lunas (lihat PelunasanService),
     * jadi keduanya selalu berjumlah persis sama dengan tagihan — total
     * keduanya harus sama dengan totalKeseluruhan().
     */
    #[Computed]
    public function totalCash(): float
    {
        return (float) $this->pesanans->sum(fn (Pesanan $p) => (float) $p->nominal_cash);
    }

    #[Computed]
    public function totalTransfer(): float
    {
        return (float) $this->pesanans->sum(fn (Pesanan $p) => (float) $p->nominal_transfer);
    }

    /**
     * Pendapatan menurut sumbernya (kategori), bukan menurut cara bayarnya
     * (cash/transfer di atas). Rute biasa dan kampas sama-sama dikelompokkan
     * "driver" karena keduanya lewat kendaraan — lihat
     * JenisPesanan::kategoriPendapatan().
     *
     * @return array<string, array{total: float, jumlah: int}>
     */
    #[Computed]
    public function totalPerKategori(): array
    {
        $grup = $this->pesanans->groupBy(fn (Pesanan $p) => $p->jenis->kategoriPendapatan());

        return [
            'driver' => [
                'total' => (float) ($grup->get('driver')?->sum->tagihan ?? 0),
                'jumlah' => $grup->get('driver')?->count() ?? 0,
            ],
            'pos' => [
                'total' => (float) ($grup->get('pos')?->sum->tagihan ?? 0),
                'jumlah' => $grup->get('pos')?->count() ?? 0,
            ],
        ];
    }

    /**
     * Riwayat pendapatan per pesanan, bukan agregat per hari — dipakai
     * tabel rincian di bawah ringkasan, terurut yang paling baru lunas
     * dulu, dan disaring lewat riwayatCari/riwayatKategori/riwayatMetode.
     *
     * Disaring di memori (bukan lewat kueri baru ke basis data): tabelnya
     * menyaring DI DALAM `pesanans()` yang sudah diambil untuk kartu
     * ringkasan di atas, bukan permintaan terpisah — rentang tanggalnya
     * sudah sama, jadi tidak ada gunanya bertanya ke basis data dua kali.
     *
     * @return Collection<int, Pesanan>
     */
    #[Computed]
    public function riwayat(): Collection
    {
        $kata = mb_strtolower(trim($this->riwayatCari));

        return $this->pesanans
            ->filter(function (Pesanan $p) use ($kata): bool {
                if ($this->riwayatKategori !== '' && $p->jenis->kategoriPendapatan() !== $this->riwayatKategori) {
                    return false;
                }

                if ($this->riwayatMetode === 'cash' && (float) $p->nominal_cash <= 0) {
                    return false;
                }

                if ($this->riwayatMetode === 'transfer' && (float) $p->nominal_transfer <= 0) {
                    return false;
                }

                if ($kata !== ''
                    && ! str_contains(mb_strtolower($p->kode), $kata)
                    && ! str_contains(mb_strtolower($p->toko->nama), $kata)) {
                    return false;
                }

                return true;
            })
            ->sortByDesc(fn (Pesanan $p) => $p->tanggal_pendapatan.$p->id)
            ->values();
    }

    #[Computed]
    public function dataChart(): array
    {
        return [
            'labels' => $this->ringkasanHarian->keys()->all(),
            'data' => $this->ringkasanHarian->values()->all(),
        ];
    }

    public function render()
    {
        return view('livewire.pembayaran.pendapatan')->title(__('pembayaran.judul_pendapatan'));
    }
}
