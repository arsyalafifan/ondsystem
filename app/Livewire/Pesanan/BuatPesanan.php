<?php

namespace App\Livewire\Pesanan;

use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\Kunjungan\PenguraiQr;
use App\Services\PesananService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class BuatPesanan extends Component
{
    public string $cariToko = '';

    /** 'ketik' untuk pencarian biasa, 'pindai' untuk membaca QR freezer. */
    public string $caraPilihToko = 'ketik';

    public ?int $tokoId = null;

    public string $catatan = '';

    /** @var array<int, array{produk_id: int|string, jumlah_dus: int|string}> */
    public array $baris = [];

    public ?string $kodeTerakhir = null;

    public function mount(): void
    {
        $this->tambahBaris();
    }

    public function tambahBaris(): void
    {
        $this->baris[] = ['produk_id' => '', 'jumlah_dus' => ''];
    }

    public function hapusBaris(int $indeks): void
    {
        unset($this->baris[$indeks]);
        $this->baris = array_values($this->baris);

        if ($this->baris === []) {
            $this->tambahBaris();
        }
    }

    public function pilihToko(int $id): void
    {
        $this->tokoId = $id;
        $this->cariToko = '';
        $this->resetValidation();
    }

    public function batalPilihToko(): void
    {
        $this->tokoId = null;
    }

    public function gantiCaraPilih(string $cara): void
    {
        $this->caraPilihToko = in_array($cara, ['ketik', 'pindai'], true) ? $cara : 'ketik';
        $this->cariToko = '';
    }

    /**
     * Memilih toko dari hasil pemindaian QR freezer.
     *
     * Nomor aset pada QR dicocokkan dengan kolom asset_id di master toko —
     * pengenal yang sama dengan yang dipakai kunjungan sales, jadi satu stiker
     * berlaku untuk kedua keperluan.
     */
    public function pilihTokoDariQr(string $isi, PenguraiQr $pengurai): void
    {
        $hasil = $pengurai->urai($isi);

        if ($hasil === null) {
            $this->tolakPindaian(__('kunjungan.galat_qr_tidak_terbaca'));

            return;
        }

        $toko = Toko::where('asset_id', $hasil->assetId)->first();

        if ($toko === null) {
            $this->tolakPindaian(__('kunjungan.galat_aset_tidak_dikenal', ['aset' => $hasil->assetId]));

            return;
        }

        if (! $toko->aktif) {
            $this->tolakPindaian(__('pesanan.galat_toko_nonaktif', ['nama' => $toko->nama]));

            return;
        }

        if ($toko->wilayah_id === null) {
            $this->tolakPindaian(__('pesanan.galat_toko_tanpa_wilayah', ['nama' => $toko->nama]));

            return;
        }

        // Toko yang masih punya pesanan berjalan tidak langsung dipilih, sama
        // seperti pada daftar hasil ketikan yang barisnya dibuat tidak bisa
        // diklik. Kalau tetap dipilih, sales baru tahu ditolaknya setelah
        // mengisi seluruh produk.
        $pesananAktif = Pesanan::where('toko_id', $toko->id)
            ->whereIn('status', StatusPesanan::aktif())
            ->first();

        if ($pesananAktif !== null) {
            $this->tolakPindaian(__('pesanan.halangan_pesanan_aktif', [
                'kode' => $pesananAktif->kode,
                'status' => $pesananAktif->status->label(),
            ]));

            return;
        }

        $this->pilihToko($toko->id);

        $this->dispatch('notifikasi', pesan: __('pesanan.notif_toko_dari_qr', [
            'nama' => $toko->nama,
            'aset' => $toko->asset_id,
        ]));
    }

    /** Menolak hasil pindaian dan mempersilakan pemindai membaca ulang. */
    private function tolakPindaian(string $pesan): void
    {
        $this->dispatch('notifikasi', pesan: $pesan, jenis: 'error');
        $this->dispatch('qr-toko-ditolak');
    }

    /**
     * Pencarian toko: nama, kode, alamat, atau nomor aset freezer.
     *
     * Nomor aset dirapikan lebih dulu — huruf besar dan tanpa spasi — supaya
     * cocok dengan bentuk yang tersimpan, apa pun cara pengetikannya. Mengetik
     * nomor aset lengkap otomatis menyisakan satu toko, karena nomor aset unik.
     *
     * @return Collection<int, Toko>
     */
    #[Computed]
    public function hasilCari(): Collection
    {
        if (mb_strlen(trim($this->cariToko)) < 2) {
            return collect();
        }

        $kata = trim($this->cariToko);
        $aset = mb_strtoupper(preg_replace('/\s+/', '', $kata) ?? '');

        return Toko::query()
            ->aktif()
            ->with('wilayah:id,nama')
            ->withCount(['pesanans as punya_pesanan_aktif' => fn ($q) => $q->whereIn('status', StatusPesanan::aktif())])
            ->where(fn ($q) => $q
                ->where('nama', 'like', "%{$kata}%")
                ->orWhere('kode', 'like', "%{$kata}%")
                ->orWhere('alamat', 'like', "%{$kata}%")
                ->orWhere('asset_id', 'like', "%{$aset}%"))
            // Kecocokan nomor aset dinaikkan ke atas: kalau seseorang mengetik
            // nomor aset, itulah yang paling mungkin dicarinya.
            ->orderByRaw('CASE WHEN asset_id = ? THEN 0 ELSE 1 END', [$aset])
            ->orderBy('nama')
            ->limit(12)
            ->get();
    }

    #[Computed]
    public function toko(): ?Toko
    {
        return $this->tokoId === null
            ? null
            : Toko::with('wilayah:id,nama')->find($this->tokoId);
    }

    /** Pesanan yang membuat toko terpilih belum boleh dipesan lagi. */
    #[Computed]
    public function pesananAktifToko(): ?Pesanan
    {
        return $this->tokoId === null
            ? null
            : Pesanan::where('toko_id', $this->tokoId)
                ->whereIn('status', StatusPesanan::aktif())
                ->first();
    }

    /** @return Collection<int, Produk> */
    #[Computed]
    public function produks(): Collection
    {
        return Produk::aktif()->orderBy('nama')->get();
    }

    #[Computed]
    public function totalDus(): int
    {
        return array_sum(array_map(fn (array $b) => (int) ($b['jumlah_dus'] ?: 0), $this->baris));
    }

    #[Computed]
    public function totalNilai(): float
    {
        $produks = $this->produks->keyBy('id');
        $total = 0.0;

        foreach ($this->baris as $b) {
            $produk = $produks->get((int) $b['produk_id']);

            if ($produk !== null) {
                $total += (float) $produk->harga * (int) ($b['jumlah_dus'] ?: 0);
            }
        }

        return $total;
    }

    /**
     * Semua yang menghalangi pesanan disimpan, dikumpulkan jadi satu daftar
     * agar sales melihat semuanya sekaligus, bukan satu per satu tiap
     * menekan simpan.
     *
     * Setiap halangan membawa penanda jenisnya, bukan hanya teksnya, supaya
     * tampilan bisa menandai pemeriksaan mana yang gagal tanpa harus
     * mencocokkan potongan kalimat — cara yang langsung rusak begitu
     * bahasanya berganti.
     *
     * @return array<int, array{jenis: string, pesan: string}>
     */
    #[Computed]
    public function halangan(): array
    {
        $masalah = [];
        $minDus = (int) config('ond.min_dus_per_toko');

        if ($this->tokoId === null) {
            $masalah[] = ['jenis' => 'toko', 'pesan' => __('pesanan.toko_belum_dipilih')];
        } elseif ($this->toko?->wilayah_id === null) {
            $masalah[] = ['jenis' => 'toko_tanpa_wilayah', 'pesan' => __('pesanan.halangan_toko_tanpa_wilayah')];
        } elseif ($this->pesananAktifToko !== null) {
            $p = $this->pesananAktifToko;
            $masalah[] = [
                'jenis' => 'pesanan_aktif',
                'pesan' => __('pesanan.halangan_pesanan_aktif', [
                    'kode' => $p->kode,
                    'status' => $p->status->label(),
                ]),
            ];
        }

        if ($this->totalDus < $minDus) {
            $masalah[] = [
                'jenis' => 'min_dus',
                'pesan' => __('pesanan.halangan_min_dus', ['min' => $minDus, 'sekarang' => $this->totalDus]),
            ];
        }

        $produks = $this->produks->keyBy('id');
        $diminta = [];

        foreach ($this->baris as $b) {
            $id = (int) $b['produk_id'];
            $jumlah = (int) ($b['jumlah_dus'] ?: 0);

            if ($id > 0 && $jumlah > 0) {
                $diminta[$id] = ($diminta[$id] ?? 0) + $jumlah;
            }
        }

        foreach ($diminta as $id => $jumlah) {
            $produk = $produks->get($id);

            if ($produk === null) {
                continue;
            }

            if ($jumlah > $produk->stok_tersedia) {
                $masalah[] = [
                    'jenis' => 'stok',
                    'pesan' => __('pesanan.galat_stok_kurang_ringkas', [
                        'nama' => $produk->nama,
                        'diminta' => $jumlah,
                        'tersedia' => $produk->stok_tersedia,
                    ]),
                ];
            }
        }

        if ($diminta === []) {
            $masalah[] = ['jenis' => 'produk', 'pesan' => __('pesanan.belum_ada_produk')];
        }

        return $masalah;
    }

    /** Apakah ada halangan dengan jenis tertentu. */
    public function adaHalangan(string $jenis): bool
    {
        foreach ($this->halangan as $h) {
            if ($h['jenis'] === $jenis) {
                return true;
            }
        }

        return false;
    }

    public function simpan(PesananService $service): void
    {
        if ($this->tokoId === null) {
            $this->addError('tokoId', __('pesanan.pilih_toko_dulu'));

            return;
        }

        try {
            $pesanan = $service->buat(
                toko: Toko::findOrFail($this->tokoId),
                items: $this->baris,
                pembuat: auth()->user(),
                catatan: $this->catatan ?: null,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom === 'items' ? 'baris' : $kolom, $pesan[0]);
            }

            $this->dispatch('notifikasi', pesan: __('pesanan.ditolak'), jenis: 'error');

            return;
        }

        $this->kodeTerakhir = $pesanan->kode;

        $this->reset(['tokoId', 'catatan', 'baris', 'cariToko']);
        $this->tambahBaris();

        $this->dispatch('notifikasi', pesan: __('pesanan.notif_tersimpan', ['kode' => $pesanan->kode]));
    }

    public function render()
    {
        return view('livewire.pesanan.buat-pesanan')->title(__('pesanan.judul_buat'));
    }
}
