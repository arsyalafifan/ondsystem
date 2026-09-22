<?php

namespace App\Livewire\Noo\Concerns;

use App\Enums\KategoriToko;
use App\Models\Noo;
use App\Models\PaketNoo;
use App\Models\Wilayah;
use App\Support\DepotContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * Formulir data calon toko, dipakai bersama oleh dua layar: sales yang
 * mendata di lapangan (App\Livewire\Noo\DaftarNoo) dan admin yang
 * mengoreksinya sebelum menyetujui (App\Livewire\Noo\Persetujuan).
 *
 * Keduanya harus memakai aturan yang SAMA PERSIS — kalau tidak, data yang
 * lolos di tangan sales bisa saja tertolak di tangan admin (atau sebaliknya)
 * tanpa ada yang tahu kenapa. Karena itu bentuk isian, aturan validasi, dan
 * cara mengubahnya jadi kolom basis data semuanya tinggal di satu tempat ini.
 */
trait PunyaFormNoo
{
    public string $nama = '';

    public string $alamat = '';

    public ?int $wilayahId = null;

    public string $kelurahan = '';

    public string $kecamatan = '';

    public string $kota = '';

    public string $provinsi = '';

    public string $kodePos = '';

    public string $telepon = '';

    public string $namaPemilik = '';

    public string $nikPemilik = '';

    public string $kategori = '';

    public string $freezerTipe = '';

    public ?int $paketNooId = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $sumberKoordinat = 'belum';

    public string $koordinatTempel = '';

    public ?string $asalKoordinat = null;

    /** @return Collection<int, Wilayah> */
    #[Computed]
    public function wilayahs(): Collection
    {
        return Wilayah::query()->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Hanya paket yang produknya sudah genap yang boleh dipilih — paket
     * timpang akan gagal jadi pesanan perdana di ujung alur nanti, saat
     * driver sudah berdiri di depan toko.
     *
     * @return Collection<int, PaketNoo>
     */
    #[Computed]
    public function paketTersedia(): Collection
    {
        return PaketNoo::query()
            ->aktif()
            ->with('items.produk:id,nama')
            ->orderBy('urutan')
            ->get()
            ->filter(fn (PaketNoo $paket): bool => $paket->lengkap)
            ->values();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function konfigPeta(): array
    {
        $depot = DepotContext::current();

        return [
            'tileUrl' => config('ond.peta.tile_url'),
            'attribution' => config('ond.peta.attribution'),
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'depot' => [
                'lat' => $depot?->lat ?? -2.5,
                'lng' => $depot?->lng ?? 118.0,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Koordinat
    // ------------------------------------------------------------------

    /** Dipanggil dari peta ketika penanda diklik atau digeser. */
    public function titikDipilih(float $lat, float $lng): void
    {
        $this->pakaiKoordinat($lat, $lng, __('master.titik_dari_peta'));
    }

    /** Dipanggil dari tombol "Lokasi Saya", setelah browser memberi izin GPS. */
    public function lokasiSayaDipilih(float $lat, float $lng): void
    {
        $this->pakaiKoordinat($lat, $lng, __('master.titik_dari_gps'));
        $this->dispatch('pindahkan-penanda', lat: $lat, lng: $lng);
    }

    /** Menerima format "lat, lng" yang biasa disalin dari Google Maps. */
    public function terapkanKoordinatTempel(): void
    {
        $this->resetErrorBag('koordinatTempel');

        $bagian = array_map('trim', explode(',', $this->koordinatTempel));

        if (count($bagian) !== 2 || ! is_numeric($bagian[0]) || ! is_numeric($bagian[1])) {
            $this->addError('koordinatTempel', __('master.koordinat_tempel_tidak_valid'));

            return;
        }

        $lat = (float) $bagian[0];
        $lng = (float) $bagian[1];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $this->addError('koordinatTempel', __('master.koordinat_tempel_tidak_valid'));

            return;
        }

        $this->pakaiKoordinat($lat, $lng, __('master.titik_dari_tempel'));
        $this->koordinatTempel = '';

        $this->dispatch('pindahkan-penanda', lat: $lat, lng: $lng);
    }

    private function pakaiKoordinat(float $lat, float $lng, string $asal): void
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
        $this->sumberKoordinat = 'manual';
        $this->asalKoordinat = $asal;
    }

    // ------------------------------------------------------------------
    // Isi & validasi
    // ------------------------------------------------------------------

    /** @return list<string> */
    private function fieldForm(): array
    {
        return [
            'nama', 'alamat', 'wilayahId', 'kelurahan', 'kecamatan', 'kota', 'provinsi', 'kodePos',
            'telepon', 'namaPemilik', 'nikPemilik', 'kategori', 'freezerTipe', 'paketNooId',
            'latitude', 'longitude', 'koordinatTempel', 'asalKoordinat',
        ];
    }

    private function kosongkanForm(): void
    {
        $this->reset($this->fieldForm());
        $this->sumberKoordinat = 'belum';
        $this->resetValidation();
    }

    private function isiFormDari(Noo $noo): void
    {
        $this->nama = $noo->nama;
        $this->alamat = $noo->alamat;
        $this->wilayahId = $noo->wilayah_id;
        $this->kelurahan = (string) $noo->kelurahan;
        $this->kecamatan = (string) $noo->kecamatan;
        $this->kota = (string) $noo->kota;
        $this->provinsi = (string) $noo->provinsi;
        $this->kodePos = (string) $noo->kode_pos;
        $this->telepon = $noo->telepon;
        $this->namaPemilik = $noo->nama_pemilik;
        $this->nikPemilik = $noo->nik_pemilik;
        $this->kategori = $noo->kategori?->value ?? '';
        $this->freezerTipe = (string) $noo->freezer_tipe;
        $this->paketNooId = $noo->paket_noo_id;
        $this->latitude = $noo->latitude;
        $this->longitude = $noo->longitude;
        $this->sumberKoordinat = $noo->sumber_koordinat;
        $this->asalKoordinat = null;
        $this->resetValidation();
    }

    /**
     * Memvalidasi isian formulir dan mengembalikan bentuk siap simpan.
     *
     * NIK dan telepon unik per depot di `tokos` (lihat Toko\LengkapiData) —
     * diperiksa sejak sekarang supaya "calon" yang ternyata sudah jadi mitra
     * ketahuan sedini mungkin, bukan baru saat toko hendak dibuat.
     *
     * @return array<string, mixed>
     */
    private function validasiForm(): array
    {
        $depotId = DepotContext::currentOrFail()->id;

        $data = $this->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'wilayahId' => 'required|exists:wilayahs,id',
            'kelurahan' => 'nullable|string|max:255',
            'kecamatan' => 'nullable|string|max:255',
            'kota' => 'nullable|string|max:255',
            'provinsi' => 'nullable|string|max:255',
            'kodePos' => 'nullable|string|max:10',
            'telepon' => ['required', 'string', 'max:30', Rule::unique('tokos', 'telepon')->where('depot_id', $depotId)],
            'namaPemilik' => 'required|string|max:255',
            'nikPemilik' => ['required', 'digits:16', Rule::unique('tokos', 'nik_pemilik')->where('depot_id', $depotId)],
            'kategori' => ['nullable', Rule::enum(KategoriToko::class)],
            'freezerTipe' => 'nullable|string|max:40',
            'paketNooId' => ['required', Rule::in($this->paketTersedia->pluck('id')->all())],
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ], [
            'nikPemilik.digits' => __('master.nik_tidak_valid'),
            'nikPemilik.unique' => __('toko.galat_nik_dipakai'),
            'telepon.unique' => __('toko.galat_hp_dipakai'),
            'latitude.required' => __('noo.galat_koordinat_wajib'),
            'longitude.required' => __('noo.galat_koordinat_wajib'),
            'paketNooId.in' => __('noo.galat_paket_tidak_tersedia'),
        ], [
            'nama' => __('noo.atr_nama_toko'),
            'alamat' => __('master.atr_alamat'),
            'wilayahId' => __('master.atr_wilayah'),
            'kelurahan' => __('master.kelurahan'),
            'kecamatan' => __('master.atr_kecamatan'),
            'kota' => __('master.atr_kota'),
            'provinsi' => __('master.atr_provinsi'),
            'kodePos' => __('master.atr_kode_pos'),
            'telepon' => __('master.atr_telepon'),
            'namaPemilik' => __('master.atr_nama_pemilik'),
            'nikPemilik' => __('master.atr_nik_pemilik'),
            'kategori' => __('master.kategori'),
            'freezerTipe' => __('noo.atr_freezer_tipe'),
            'paketNooId' => __('noo.atr_paket'),
        ]);

        return [
            'nama' => $data['nama'],
            'alamat' => $data['alamat'],
            'wilayah_id' => $data['wilayahId'],
            'kelurahan' => $data['kelurahan'] ?: null,
            'kecamatan' => $data['kecamatan'] ?: null,
            'kota' => $data['kota'] ?: null,
            'provinsi' => $data['provinsi'] ?: null,
            'kode_pos' => $data['kodePos'] ?: null,
            'telepon' => $data['telepon'],
            'nama_pemilik' => $data['namaPemilik'],
            'nik_pemilik' => $data['nikPemilik'],
            'kategori' => $data['kategori'] ?: null,
            'freezer_tipe' => $data['freezerTipe'] ?: null,
            'paket_noo_id' => $data['paketNooId'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'sumber_koordinat' => $this->sumberKoordinat,
        ];
    }
}
