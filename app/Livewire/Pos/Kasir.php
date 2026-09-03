<?php

namespace App\Livewire\Pos;

use App\Models\Produk;
use App\Models\Toko;
use App\Services\PesananService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Point of Sale — penjualan langsung di tempat, bukan lewat pengantaran
 * driver. Toko tetap dipilih (barangnya tercatat masuk ke toko yang mana),
 * tapi tidak ada rute, tidak ada minimal pembelian, dan uangnya diterima
 * seketika, bukan menunggu pelunasan belakangan.
 *
 * Sengaja dibuat sebagai layar tersendiri, bukan opsi di Input Pesanan
 * biasa: alur normal punya banyak aturan yang tidak relevan di sini (batas
 * minimal dus, satu pesanan aktif per toko, reservasi stok menunggu
 * pengiriman) dan mencampurnya lewat percabangan kondisi akan membuat kedua
 * alur sama-sama lebih sulit dibaca.
 */
class Kasir extends Component
{
    public string $cariToko = '';

    public ?int $tokoId = null;

    public string $catatan = '';

    /** @var array<int, array{produk_id: int|string, jumlah_dus: int|string}> */
    public array $baris = [];

    /**
     * Khusus admin/superadmin: item bonus untuk toko yang berhak — harganya
     * SELALU 0 di layar ini (lihat totalDusBonus()/simpan()), sama seperti
     * langkah bonus di Input Pesanan. Bedanya dari sana, POS tidak punya
     * langkah "atas nama sales" tersendiri — notanya tetap bisa dicetak
     * (lihat `Pesanan::bisa_dicetak`), tapi atribusinya otomatis memakai
     * `dibuat_oleh` (fallback `$pesanan->sales ?? $pesanan->pembuat` di
     * templat nota), bukan sales yang dipilih manual seperti di Input
     * Pesanan.
     *
     * @var array<int, array{produk_id: int|string, jumlah_dus: int|string}>
     */
    public array $barisBonus = [];

    /**
     * Khusus admin/superadmin: transaksi tidak diikat ke toko sungguhan
     * mana pun. BUKAN jenis transaksi khusus — produk, bonus, maupun
     * pembayarannya tetap persis sama seperti transaksi POS biasa,
     * satu-satunya beda toko tidak wajib diisi. Toko-nya memakai
     * Toko::internal() — satu baris toko semu yang tersembunyi dari
     * pencarian toko biasa (lihat dokumentasi Toko::internal()), bukan
     * toko_id yang benar-benar NULL.
     */
    public bool $tanpaToko = false;

    /**
     * Barcode diketik atau dipindai lewat alat pemindai USB/Bluetooth, yang
     * bagi peramban tidak beda dari mengetik cepat lalu menekan Enter — jadi
     * tidak perlu kamera untuk sudah bisa dipakai. Kolom `produks.barcode`
     * memang belum diisi untuk produk mana pun sampai admin melengkapinya
     * lewat Master Produk, tapi jalurnya sudah siap dipakai begitu barcode
     * pertama ditempelkan.
     */
    public string $cariBarcode = '';

    /**
     * Hanya cash untuk sekarang — opsi transfer sengaja belum ada (belum
     * dibutuhkan operasional). Diketik manual, bukan otomatis diisi penuh:
     * dus yang benar-benar dibayar kadang tidak sama persis dengan total
     * belanja di layar (mis. pembulatan uang fisik), jadi admin/sales yang
     * menentukan angkanya sendiri, sama seperti pola Pelunasan.
     */
    public string $nominalCash = '';

    public ?string $kodeTerakhir = null;

    public ?int $idTerakhir = null;

    public function mount(): void
    {
        $this->tambahBaris();

        if ($this->bisaInputBonus()) {
            $this->tambahBarisBonus();
        }
    }

    /** Hanya admin/superadmin yang punya langkah bonus — sales sama sekali tidak melihatnya. */
    public function bisaInputBonus(): bool
    {
        return auth()->user()->isAdmin();
    }

    /** Hanya admin/superadmin yang punya opsi "Tanpa Toko" — sales sama sekali tidak melihatnya. */
    public function bisaTanpaToko(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function aktifkanTanpaToko(): void
    {
        if (! $this->bisaTanpaToko()) {
            return;
        }

        $this->tanpaToko = true;
        $this->tokoId = Toko::internal()->id;
        $this->cariToko = '';
        $this->resetValidation();
    }

    public function nonaktifkanTanpaToko(): void
    {
        $this->tanpaToko = false;
        $this->tokoId = null;
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

    public function pilihToko(int $id): void
    {
        $this->tokoId = $id;
        $this->tanpaToko = false;
        $this->cariToko = '';
        $this->resetValidation();
    }

    public function batalPilihToko(): void
    {
        $this->tokoId = null;
        $this->tanpaToko = false;
    }

    /**
     * Mencari produk lewat barcode-nya, lalu menambah satu baris baru atau
     * menambah jumlah baris yang sudah ada untuk produk yang sama —
     * persis kelaziman pemindai di kasir sungguhan: tiap pindai berarti
     * "tambah satu lagi", bukan menimpa jumlah yang sudah diketik.
     */
    public function tambahDariBarcode(): void
    {
        $kode = trim($this->cariBarcode);
        $this->cariBarcode = '';

        if ($kode === '') {
            return;
        }

        $produk = Produk::aktif()->where('barcode', $kode)->first();

        if ($produk === null) {
            $this->dispatch('notifikasi', pesan: __('pos.barcode_tidak_dikenali', ['kode' => $kode]), jenis: 'error');

            return;
        }

        foreach ($this->baris as $i => $b) {
            if ((int) ($b['produk_id'] ?: 0) === $produk->id) {
                $this->baris[$i]['jumlah_dus'] = (int) ($b['jumlah_dus'] ?: 0) + 1;

                $this->dispatch('notifikasi', pesan: __('pos.notif_barcode_ditambah', ['nama' => $produk->nama]));

                return;
            }
        }

        // Baris kosong yang belum diisi produk apa pun (mis. sisa dari
        // tambahBaris() di mount()) dipakai lebih dulu, supaya barcode
        // pertama tidak menyisakan baris kosong yang mengganggu di bawahnya.
        foreach ($this->baris as $i => $b) {
            if (($b['produk_id'] ?: '') === '') {
                $this->baris[$i] = ['produk_id' => $produk->id, 'jumlah_dus' => 1];

                $this->dispatch('notifikasi', pesan: __('pos.notif_barcode_ditambah', ['nama' => $produk->nama]));

                return;
            }
        }

        $this->baris[] = ['produk_id' => $produk->id, 'jumlah_dus' => 1];

        $this->dispatch('notifikasi', pesan: __('pos.notif_barcode_ditambah', ['nama' => $produk->nama]));
    }

    public function isiTotalBelanja(): void
    {
        $this->nominalCash = (string) round($this->totalNilai, 2);
    }

    /**
     * Pencarian toko: nama, kode, atau alamat. Tidak menyaring toko yang
     * masih punya pesanan pengantaran aktif — POS tidak bersinggungan
     * dengan routing sama sekali, jadi aturan itu tidak relevan di sini.
     *
     * @return Collection<int, Toko>
     */
    #[Computed]
    public function hasilCariToko(): Collection
    {
        if (mb_strlen(trim($this->cariToko)) < 2) {
            return collect();
        }

        $kata = trim($this->cariToko);

        return Toko::query()
            ->aktif()
            ->with('wilayah:id,nama')
            ->where(fn ($q) => $q
                ->where('nama', 'like', "%{$kata}%")
                ->orWhere('kode', 'like', "%{$kata}%")
                ->orWhere('alamat', 'like', "%{$kata}%"))
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

    /**
     * Dus bonus tetap dus fisik yang sungguh keluar dari rak — dihitung
     * terpisah dari totalDus() supaya panel tetap jelas mana yang
     * ditagihkan dan mana yang bonus.
     */
    #[Computed]
    public function totalDusBonus(): int
    {
        return array_sum(array_map(fn (array $b) => (int) ($b['jumlah_dus'] ?: 0), $this->barisBonus));
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
     * Selisih antara total belanja dan nominal cash yang diisi. Nol berarti
     * pas, dipakai mengunci tombol Simpan.
     */
    #[Computed]
    public function selisihNominal(): float
    {
        return round($this->totalNilai - (float) ($this->nominalCash ?: 0), 2);
    }

    /**
     * Semua yang menghalangi transaksi disimpan, dikumpulkan jadi satu
     * daftar — pola yang sama dengan BuatPesanan::halangan().
     *
     * @return array<int, array{jenis: string, pesan: string}>
     */
    #[Computed]
    public function halangan(): array
    {
        $masalah = [];

        if ($this->tokoId === null) {
            $masalah[] = ['jenis' => 'toko', 'pesan' => __('pesanan.toko_belum_dipilih')];
        } elseif ($this->toko?->wilayah_id === null) {
            $masalah[] = ['jenis' => 'toko_tanpa_wilayah', 'pesan' => __('pesanan.halangan_toko_tanpa_wilayah')];
        }

        $produks = $this->produks->keyBy('id');
        $diminta = [];

        // Sama seperti BuatPesanan::halangan(): stok diperiksa atas
        // permintaan GABUNGAN biasa+bonus per produk, bukan dua kali
        // terpisah — produk yang sama boleh muncul di kedua daftar dan
        // keduanya berbagi rak yang sama.
        $sumberBaris = $this->bisaInputBonus() ? [...$this->baris, ...$this->barisBonus] : $this->baris;

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

            // Sama seperti PesananService::buatPos(): dibandingkan dengan
            // stok_tersedia (stok - stok_reserved), BUKAN stok fisik mentah
            // — dus yang sedang terkunci untuk pesanan pengantaran lain
            // (termasuk sisa kampas yang masih di mobil) belum benar-benar
            // ada di rak, jadi tidak boleh dijanjikan dua kali lewat POS.
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

        if ($diminta !== [] && $this->selisihNominal !== 0.0) {
            $masalah[] = ['jenis' => 'nominal', 'pesan' => __('pos.halangan_nominal_belum_pas')];
        }

        return $masalah;
    }

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

        // Bonus dan "Tanpa Toko" TIDAK PERNAH dikirim ke service kalau
        // penggunanya bukan admin/superadmin — bukan sekadar disembunyikan
        // di tampilan. State komponen ($barisBonus/$tanpaToko) tidak pernah
        // dipercaya begitu saja; siapa yang benar-benar login itulah yang
        // menentukan, sama seperti BuatPesanan::simpan().
        $bisaBonus = $this->bisaInputBonus();
        $tanpaToko = $this->bisaTanpaToko() && $this->tanpaToko;

        try {
            $pesanan = $service->buatPos(
                // "Tanpa Toko" cuma menentukan tokonya, bukan mengubah
                // apa pun yang lain — produk, bonus, dan pembayaran tetap
                // persis sama seperti transaksi POS yang memilih toko
                // sungguhan.
                toko: $tanpaToko ? Toko::internal() : Toko::findOrFail($this->tokoId),
                items: $this->baris,
                penjual: auth()->user(),
                nominalCash: (float) ($this->nominalCash ?: 0),
                nominalTransfer: 0.0,
                catatan: $this->catatan ?: null,
                bonusItems: $bisaBonus ? $this->barisBonus : [],
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError($kolom === 'items' ? 'baris' : $kolom, $pesan[0]);
            }

            $this->dispatch('notifikasi', pesan: __('pos.ditolak'), jenis: 'error');

            return;
        }

        $this->kodeTerakhir = $pesanan->kode;
        $this->idTerakhir = $pesanan->id;

        $this->reset(['tokoId', 'catatan', 'baris', 'barisBonus', 'tanpaToko', 'cariToko', 'nominalCash', 'cariBarcode']);
        $this->tambahBaris();

        if ($bisaBonus) {
            $this->tambahBarisBonus();
        }

        $this->dispatch('notifikasi', pesan: __('pos.notif_tersimpan', ['kode' => $pesanan->kode]));
    }

    public function render()
    {
        return view('livewire.pos.kasir')->title(__('pos.judul'));
    }
}
