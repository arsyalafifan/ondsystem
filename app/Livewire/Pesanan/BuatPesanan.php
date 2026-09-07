<?php

namespace App\Livewire\Pesanan;

use App\Enums\StatusPesanan;
use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Promo;
use App\Models\Toko;
use App\Models\User;
use App\Services\Kunjungan\PenguraiQr;
use App\Services\PesananService;
use App\Support\DepotContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class BuatPesanan extends Component
{
    use MembutuhkanDepotTerkunci;

    public string $cariToko = '';

    /** 'ketik' untuk pencarian biasa, 'pindai' untuk membaca QR freezer. */
    public string $caraPilihToko = 'ketik';

    public ?int $tokoId = null;

    public string $catatan = '';

    /** @var array<int, array{produk_id: int|string, jumlah_dus: int|string}> */
    public array $baris = [];

    /**
     * Langkah 3, khusus admin/superadmin: item bonus untuk toko yang
     * berhak — harganya SELALU 0 di layar ini (lihat totalNilaiBonus()),
     * bukan cuma nol karena kebetulan produknya gratis. Sales sama sekali
     * tidak melihat langkah ini; lihat bisaInputBonus().
     *
     * @var array<int, array{produk_id: int|string, jumlah_dus: int|string}>
     */
    public array $barisBonus = [];

    /**
     * Item bonus dari promo yang sedang aktif (lihat promoAktif()) — beda
     * dari $barisBonus: terbuka untuk SEMUA peran (bukan cuma
     * admin/superadmin), cuma bisa diisi begitu pesanan memenuhi syarat
     * (lihat memenuhiSyaratPromo()), dan dibatasi ke produk yang berhak
     * pada promo itu sampai maksimal Promo::bonus_dus.
     *
     * @var array<int, array{produk_id: int|string, jumlah_dus: int|string}>
     */
    public array $barisPromoBonus = [];

    /**
     * "Atas nama sales siapa" — wajib diisi untuk admin/superadmin karena
     * merekalah yang mengetik, bukan sales, tapi faktur tetap perlu
     * menampilkan nama sales yang sebenarnya bertanggung jawab.
     */
    public ?int $salesId = null;

    public ?string $kodeTerakhir = null;

    public function mount(): void
    {
        if (! $this->pastikanDepotTerkunci()) {
            return;
        }

        $this->tambahBaris();

        if ($this->bisaInputBonus()) {
            $this->tambahBarisBonus();
        }

        if ($this->promoAktif !== null) {
            $this->tambahBarisPromoBonus();
        }
    }

    /** Hanya admin/superadmin yang punya langkah bonus — sales sama sekali tidak melihatnya. */
    public function bisaInputBonus(): bool
    {
        return auth()->user()->isAdmin();
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

    public function tambahBarisBonus(): void
    {
        $this->barisBonus[] = ['produk_id' => '', 'jumlah_dus' => ''];
    }

    public function hapusBarisBonus(int $indeks): void
    {
        unset($this->barisBonus[$indeks]);
        $this->barisBonus = array_values($this->barisBonus);

        if ($this->barisBonus === []) {
            $this->tambahBarisBonus();
        }
    }

    public function tambahBarisPromoBonus(): void
    {
        $this->barisPromoBonus[] = ['produk_id' => '', 'jumlah_dus' => ''];
    }

    public function hapusBarisPromoBonus(int $indeks): void
    {
        unset($this->barisPromoBonus[$indeks]);
        $this->barisPromoBonus = array_values($this->barisPromoBonus);

        if ($this->barisPromoBonus === []) {
            $this->tambahBarisPromoBonus();
        }
    }

    /**
     * Mengosongkan pilihan bonus promo begitu total dus reguler turun lagi
     * di bawah ambang — sekadar kenyamanan tampilan (baris yang sudah
     * terisi tidak nyangkut dalam keadaan disabled), BUKAN satu-satunya
     * pertahanan: simpan() sama sekali tidak pernah mengirim barisPromoBonus
     * ke service kalau memenuhiSyaratPromo() sedang false, apa pun isi
     * state komponen ini.
     *
     * Sebaliknya, begitu BARU memenuhi syarat dan baris promo masih kosong
     * (baru saja dikosongkan tadi, atau memang belum pernah diisi), satu
     * baris kosong langsung disediakan — sama seperti mount() menyediakan
     * baris pertama. Tanpa ini, baris kosong tidak pernah muncul lagi
     * setelah sempat dikosongkan, dan `set()` langsung ke indeks yang tidak
     * ada (baik dari pengujian maupun dari input yang terlanjur terkirim
     * sebelum baris sempat digambar ulang) akan membuat baris cacat yang
     * cuma punya sebagian kolom, bukan {produk_id, jumlah_dus} lengkap.
     */
    public function updatedBaris(): void
    {
        if (! $this->memenuhiSyaratPromo) {
            $this->barisPromoBonus = [];
        } elseif ($this->barisPromoBonus === []) {
            $this->tambahBarisPromoBonus();
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
     * Dus bonus tetap dus fisik yang sungguh dimuat ke mobil — dihitung
     * terpisah dari totalDus() (bukan digabung) supaya panel tetap jelas
     * mana yang ditagihkan dan mana yang bonus, tapi tetap ikut dihitung
     * ke batas minimal pesanan lewat halangan() di bawah.
     */
    #[Computed]
    public function totalDusBonus(): int
    {
        return array_sum(array_map(fn (array $b) => (int) ($b['jumlah_dus'] ?: 0), $this->barisBonus));
    }

    /**
     * Promo yang periodenya mencakup hari ini, kalau ada — null berarti
     * tidak ada promo yang sedang berjalan, dan seluruh bagian promo bonus
     * di layar ini tersembunyi total. Beda dari promoAktif() di
     * PesananService (yang dipanggil ulang saat simpan(), tidak pernah
     * mempercayai hasil computed ini) — computed ini murni untuk tampilan.
     */
    #[Computed]
    public function promoAktif(): ?Promo
    {
        return Promo::query()->with('produks')->aktifPada(today()->toDateString())->first();
    }

    /**
     * Pesanan memenuhi syarat promo begitu total dus REGULER (bukan bonus
     * apa pun) sudah mencapai ambang minimal_dus promo yang aktif — sama
     * seperti validasi di PesananService::buat(), $this->totalDus di sini
     * cuma menjumlahkan $baris, tidak ikut menghitung barisBonus maupun
     * barisPromoBonus.
     */
    #[Computed]
    public function memenuhiSyaratPromo(): bool
    {
        return $this->promoAktif !== null && $this->totalDus >= $this->promoAktif->minimal_dus;
    }

    #[Computed]
    public function totalDusPromoBonus(): int
    {
        return array_sum(array_map(fn (array $b) => (int) ($b['jumlah_dus'] ?: 0), $this->barisPromoBonus));
    }

    /** Daftar sales untuk "atas nama sales" — cuma dipakai kalau bisaInputBonus(). */
    #[Computed]
    public function salesList(): Collection
    {
        return User::sales()->orderBy('name')->get(['id', 'name']);
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
    /**
     * Dipakai juga dari resources/views/livewire/pesanan/buat-pesanan.blade.php
     * — panel validasi menampilkan angka ini di teksnya. Blade view tidak
     * bisa memanggil DepotContext langsung tanpa impor kelas yang canggung,
     * jadi diekspos lewat computed property di sini saja.
     */
    #[Computed]
    public function minDusPerToko(): int
    {
        return DepotContext::currentOrFail()->min_dus_per_toko;
    }

    #[Computed]
    public function halangan(): array
    {
        $masalah = [];
        $minDus = $this->minDusPerToko();

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

        // Dus bonus (manual maupun promo) tetap dus fisik yang sungguh
        // dimuat ke mobil, jadi ikut dihitung ke batas minimal — sama
        // seperti PesananService::buat().
        $totalDusGabungan = $this->totalDus
            + ($this->bisaInputBonus() ? $this->totalDusBonus : 0)
            + ($this->memenuhiSyaratPromo ? $this->totalDusPromoBonus : 0);

        if ($totalDusGabungan < $minDus) {
            $masalah[] = [
                'jenis' => 'min_dus',
                'pesan' => __('pesanan.halangan_min_dus', ['min' => $minDus, 'sekarang' => $totalDusGabungan]),
            ];
        }

        if ($this->bisaInputBonus() && $this->salesId === null) {
            $masalah[] = ['jenis' => 'sales', 'pesan' => __('pesanan.halangan_sales_wajib')];
        }

        if ($this->memenuhiSyaratPromo && $this->totalDusPromoBonus > $this->promoAktif->bonus_dus) {
            $masalah[] = [
                'jenis' => 'promo_bonus_lebih',
                'pesan' => __('pesanan.halangan_promo_melebihi_batas', ['maks' => $this->promoAktif->bonus_dus]),
            ];
        }

        if ($this->memenuhiSyaratPromo) {
            $produkLayakPromo = $this->promoAktif->produks->pluck('id')->all();

            foreach ($this->barisPromoBonus as $b) {
                $id = (int) $b['produk_id'];

                if ($id > 0 && ! in_array($id, $produkLayakPromo, true)) {
                    $masalah[] = ['jenis' => 'promo_bonus_produk', 'pesan' => __('pesanan.halangan_promo_produk_tak_layak')];
                    break;
                }
            }
        }

        $produks = $this->produks->keyBy('id');
        // Sama seperti PesananService::buat(): stok diperiksa atas
        // permintaan GABUNGAN biasa+bonus manual+bonus promo per produk,
        // bukan dipisah-pisah — produk yang sama boleh muncul di beberapa
        // daftar sekaligus, dan semuanya berbagi rak yang sama.
        $diminta = [];

        $sumberBaris = [
            ...$this->baris,
            ...($this->bisaInputBonus() ? $this->barisBonus : []),
            ...($this->memenuhiSyaratPromo ? $this->barisPromoBonus : []),
        ];

        foreach ($sumberBaris as $b) {
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
        if ($this->depotBelumDipilih) {
            $this->addError('tokoId', __('umum.butuh_depot_aksi'));

            return;
        }

        if ($this->tokoId === null) {
            $this->addError('tokoId', __('pesanan.pilih_toko_dulu'));

            return;
        }

        // Bonus manual, "atas nama sales", dan bonus promo TIDAK PERNAH
        // dikirim ke service kalau syaratnya tidak terpenuhi di server —
        // bukan sekadar disembunyikan di tampilan. State komponen
        // ($barisBonus/$salesId/$barisPromoBonus) tidak pernah dipercaya
        // begitu saja; siapa yang benar-benar login dan promo yang benar-
        // benar aktif itulah yang menentukan, sama seperti
        // pastikanBisaBertindak() di DaftarKunjungan untuk kasus serupa.
        $bisaBonus = $this->bisaInputBonus();
        $promoBerlaku = $this->memenuhiSyaratPromo;

        try {
            $pesanan = $service->buat(
                toko: Toko::findOrFail($this->tokoId),
                items: $this->baris,
                pembuat: auth()->user(),
                catatan: $this->catatan ?: null,
                bonusItems: $bisaBonus ? $this->barisBonus : [],
                atasNamaSales: $bisaBonus && $this->salesId !== null ? User::find($this->salesId) : null,
                promoBonusItems: $promoBerlaku ? $this->barisPromoBonus : [],
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $kolomTampil = match ($kolom) {
                    'items' => 'baris',
                    'atasNamaSales' => 'salesId',
                    'promoBonusItems' => 'barisPromoBonus',
                    default => $kolom,
                };

                $this->addError($kolomTampil, $pesan[0]);
            }

            $this->dispatch('notifikasi', pesan: __('pesanan.ditolak'), jenis: 'error');

            return;
        }

        $this->kodeTerakhir = $pesanan->kode;

        $this->reset(['tokoId', 'catatan', 'baris', 'barisBonus', 'barisPromoBonus', 'salesId', 'cariToko']);
        $this->tambahBaris();

        if ($bisaBonus) {
            $this->tambahBarisBonus();
        }

        if ($this->promoAktif !== null) {
            $this->tambahBarisPromoBonus();
        }

        $this->dispatch('notifikasi', pesan: __('pesanan.notif_tersimpan', ['kode' => $pesanan->kode]));
    }

    public function render()
    {
        return view('livewire.pesanan.buat-pesanan')->title(__('pesanan.judul_buat'));
    }
}
