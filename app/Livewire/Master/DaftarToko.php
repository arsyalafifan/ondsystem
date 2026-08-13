<?php

namespace App\Livewire\Master;

use App\Enums\StatusPesanan;
use App\Models\Toko;
use App\Models\Wilayah;
use App\Services\Peta\NominatimGeocoder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Master toko, termasuk pekerjaan yang paling menentukan mutu routing:
 * memastikan setiap toko punya titik koordinat.
 *
 * Ada tiga jalan melengkapi koordinat, dari yang paling cepat:
 *  - impor CSV yang sudah berisi lat/lng;
 *  - pencarian alamat otomatis, satu per satu maupun sekaligus;
 *  - menaruh penanda langsung di peta, untuk alamat yang tidak terbaca mesin.
 */
class DaftarToko extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(as: 'q')]
    public string $cari = '';

    #[Url(as: 'wilayah')]
    public string $filterWilayah = '';

    #[Url]
    public bool $tanpaKoordinat = false;

    // --- Formulir ---
    public ?int $tokoId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $assetId = '';

    public string $freezerTipe = '';

    public string $nama = '';

    public string $alamat = '';

    public ?int $wilayahId = null;

    public string $kelurahan = '';

    public string $kecamatan = '';

    public string $kota = '';

    public string $kodePos = '';

    public string $telepon = '';

    public string $namaPemilik = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $sumberKoordinat = 'belum';

    public bool $aktif = true;

    public ?string $hasilGeocode = null;

    public string $koordinatTempel = '';

    // --- Impor ---
    public bool $imporTerbuka = false;

    public $berkasCsv;

    /** @var array<string, mixed>|null */
    public ?array $hasilImpor = null;

    public bool $sedangGeocodeMassal = false;

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['cari', 'filterWilayah', 'tanpaKoordinat'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function tokos()
    {
        return Toko::query()
            ->with('wilayah:id,nama')
            ->withCount(['pesanans as pesanan_aktif' => fn ($q) => $q->whereIn('status', StatusPesanan::aktif())])
            ->when($this->filterWilayah !== '', fn ($q) => $q->where('wilayah_id', $this->filterWilayah))
            ->when($this->tanpaKoordinat, fn ($q) => $q->tanpaKoordinat())
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$this->cari}%")
                ->orWhere('kode', 'like', "%{$this->cari}%")
                ->orWhere('asset_id', 'like', "%{$this->cari}%")
                ->orWhere('alamat', 'like', "%{$this->cari}%")))
            ->orderBy('nama')
            ->paginate(20);
    }

    #[Computed]
    public function wilayahs()
    {
        return Wilayah::aktif()->orderBy('nama')->get(['id', 'nama']);
    }

    #[Computed]
    public function jumlahTanpaKoordinat(): int
    {
        return Toko::aktif()->tanpaKoordinat()->count();
    }

    #[Computed]
    public function konfigPeta(): array
    {
        return [
            'tileUrl' => config('ond.peta.tile_url'),
            'attribution' => config('ond.peta.attribution'),
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'depot' => [
                'lat' => (float) config('ond.depot.lat'),
                'lng' => (float) config('ond.depot.lng'),
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Formulir
    // ------------------------------------------------------------------

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->kode = $this->kodeBerikutnya();
        $this->wilayahId = $this->wilayahs->first()?->id;
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $toko = Toko::findOrFail($id);

        $this->tokoId = $toko->id;
        $this->kode = $toko->kode;
        $this->assetId = $toko->asset_id ?? '';
        $this->freezerTipe = $toko->freezer_tipe ?? '';
        $this->nama = $toko->nama;
        $this->alamat = $toko->alamat;
        $this->wilayahId = $toko->wilayah_id;
        $this->kelurahan = $toko->kelurahan ?? '';
        $this->kecamatan = $toko->kecamatan ?? '';
        $this->kota = $toko->kota ?? '';
        $this->kodePos = $toko->kode_pos ?? '';
        $this->telepon = $toko->telepon ?? '';
        $this->namaPemilik = $toko->nama_pemilik ?? '';
        $this->latitude = $toko->latitude;
        $this->longitude = $toko->longitude;
        $this->sumberKoordinat = $toko->sumber_koordinat;
        $this->aktif = $toko->aktif;
        $this->hasilGeocode = null;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset([
            'tokoId', 'kode', 'assetId', 'freezerTipe', 'nama', 'alamat', 'wilayahId',
            'kelurahan', 'kecamatan', 'kota', 'kodePos', 'telepon', 'namaPemilik',
            'latitude', 'longitude', 'hasilGeocode', 'koordinatTempel',
        ]);

        $this->sumberKoordinat = 'belum';
        $this->aktif = true;
        $this->resetValidation();
    }

    /** Dipanggil dari peta ketika admin mengklik atau menggeser penanda. */
    public function titikDipilih(float $lat, float $lng): void
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
        $this->sumberKoordinat = 'manual';
        $this->hasilGeocode = __('master.titik_dari_peta');
    }

    /** Dipanggil dari tombol "Lokasi Saya", setelah browser memberi izin GPS. */
    public function lokasiSayaDipilih(float $lat, float $lng): void
    {
        $this->latitude = $lat;
        $this->longitude = $lng;
        $this->sumberKoordinat = 'manual';
        $this->hasilGeocode = __('master.titik_dari_gps');

        $this->dispatch('pindahkan-penanda', lat: $lat, lng: $lng);
    }

    /**
     * Menerima format "lat, lng" yang biasa disalin langsung dari Google Maps,
     * misalnya "1.0574377910177943, 104.03874610943407".
     */
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

        $this->latitude = $lat;
        $this->longitude = $lng;
        $this->sumberKoordinat = 'manual';
        $this->hasilGeocode = __('master.titik_dari_tempel');
        $this->koordinatTempel = '';

        $this->dispatch('pindahkan-penanda', lat: $lat, lng: $lng);
    }

    /** Mencari koordinat dari alamat yang sedang diketik. */
    public function cariKoordinat(NominatimGeocoder $geocoder): void
    {
        $alamat = collect([$this->alamat, $this->kelurahan, $this->kecamatan, $this->kota, $this->kodePos])
            ->filter()->implode(', ');

        if (trim($alamat) === '') {
            $this->addError('alamat', __('master.isi_alamat_dulu'));

            return;
        }

        $hasil = $geocoder->cari($alamat);

        if ($hasil === null) {
            $this->hasilGeocode = __('master.geocode_gagal');
            $this->dispatch('notifikasi', pesan: __('master.geocode_gagal_notif'), jenis: 'error');

            return;
        }

        $this->latitude = $hasil['lat'];
        $this->longitude = $hasil['lng'];
        $this->sumberKoordinat = 'geocode';
        $this->hasilGeocode = __('master.geocode_ditemukan', [
            'tingkat' => $hasil['tingkat'],
            'alamat' => $hasil['display_name'],
        ]);

        // Hasil setingkat kota atau provinsi hanya menunjuk pusat wilayah,
        // bukan lokasi toko. Admin perlu tahu itu supaya rutenya tidak meleset.
        if (in_array($hasil['tingkat'], ['city', 'state', 'province', 'county', 'administrative'], true)) {
            $this->hasilGeocode .= __('master.geocode_terlalu_umum');
        }

        $this->dispatch('pindahkan-penanda', lat: $hasil['lat'], lng: $hasil['lng']);
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'kode' => ['required', 'string', 'max:30', Rule::unique('tokos', 'kode')->ignore($this->tokoId)],
            // Nomor aset boleh kosong selama freezernya belum terpasang, tapi
            // begitu diisi harus unik: inilah pengenal toko saat sales memindai.
            'assetId' => ['nullable', 'string', 'max:40', Rule::unique('tokos', 'asset_id')->ignore($this->tokoId)],
            'freezerTipe' => 'nullable|string|max:40',
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'wilayahId' => 'required|exists:wilayahs,id',
            'kelurahan' => 'nullable|string|max:255',
            'kecamatan' => 'nullable|string|max:255',
            'kota' => 'nullable|string|max:255',
            'kodePos' => 'nullable|string|max:10',
            'telepon' => 'nullable|string|max:30',
            'namaPemilik' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ], [], [
            'kode' => __('master.atr_kode_toko'),
            'assetId' => __('master.atr_asset_id'),
            'nama' => __('master.atr_nama_toko'),
            'wilayahId' => __('master.atr_wilayah'),
            'kodePos' => __('master.atr_kode_pos'),
            'namaPemilik' => __('master.atr_nama_pemilik'),
        ]);

        Toko::updateOrCreate(['id' => $this->tokoId], [
            'kode' => $data['kode'],
            // Disimpan huruf besar tanpa spasi agar cocok dengan hasil
            // pemindaian QR, yang juga dirapikan dengan cara yang sama.
            'asset_id' => $this->assetId === '' ? null : mb_strtoupper(preg_replace('/\s+/', '', $this->assetId)),
            'freezer_tipe' => $this->freezerTipe ?: null,
            'nama' => $data['nama'],
            'alamat' => $data['alamat'],
            'wilayah_id' => $data['wilayahId'],
            'kelurahan' => $this->kelurahan ?: null,
            'kecamatan' => $this->kecamatan ?: null,
            'kota' => $this->kota ?: null,
            'kode_pos' => $this->kodePos ?: null,
            'telepon' => $this->telepon ?: null,
            'nama_pemilik' => $this->namaPemilik ?: null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'sumber_koordinat' => $this->latitude === null ? 'belum' : $this->sumberKoordinat,
            'geocoded_at' => $this->latitude === null ? null : now(),
            'aktif' => $this->aktif,
        ]);

        $pesan = $this->tokoId === null ? __('master.toko_tersimpan') : __('master.toko_diperbarui');

        $this->tutupForm();
        unset($this->tokos, $this->jumlahTanpaKoordinat);

        $this->dispatch('notifikasi', pesan: $pesan);
    }

    /**
     * Mencari koordinat untuk semua toko yang belum punya, sekaligus.
     *
     * Nominatim membatasi satu permintaan per detik, jadi sekali jalan
     * dibatasi jumlahnya agar halaman tidak menggantung terlalu lama.
     */
    public function geocodeMassal(NominatimGeocoder $geocoder): void
    {
        $batasSekaliJalan = 20;

        $tokos = Toko::aktif()->tanpaKoordinat()->limit($batasSekaliJalan)->get();

        if ($tokos->isEmpty()) {
            $this->dispatch('notifikasi', pesan: __('master.semua_berkoordinat'), jenis: 'info');

            return;
        }

        $berhasil = 0;
        $gagal = 0;

        foreach ($tokos as $toko) {
            $hasil = $geocoder->cari($toko->alamat_lengkap);

            if ($hasil === null) {
                $toko->update(['geocode_catatan' => __('master.geocode_tidak_ditemukan_catatan', [
                    'waktu' => now()->format('d M Y H:i'),
                ])]);
                $gagal++;

                continue;
            }

            $toko->update([
                'latitude' => $hasil['lat'],
                'longitude' => $hasil['lng'],
                'sumber_koordinat' => 'geocode',
                'geocoded_at' => now(),
                'geocode_catatan' => __('master.geocode_tingkat_catatan', [
                    'tingkat' => $hasil['tingkat'],
                    'alamat' => $hasil['display_name'],
                ]),
            ]);

            $berhasil++;
        }

        $sisa = Toko::aktif()->tanpaKoordinat()->count();
        unset($this->tokos, $this->jumlahTanpaKoordinat);

        $pesan = __('master.hasil_geocode_massal', ['berhasil' => $berhasil, 'gagal' => $gagal]);

        if ($sisa > 0) {
            $pesan .= ' '.__('master.sisa_geocode', ['jumlah' => $sisa]);
        }

        $this->dispatch('notifikasi', pesan: $pesan, jenis: $gagal > 0 ? 'info' : 'sukses');
    }

    // ------------------------------------------------------------------
    // Impor CSV
    // ------------------------------------------------------------------

    public function imporCsv(): void
    {
        $this->validate([
            'berkasCsv' => 'required|file|mimes:csv,txt|max:5120',
        ], [
            'berkasCsv.required' => __('master.pilih_csv_dulu'),
            'berkasCsv.mimes' => __('master.harus_csv'),
        ]);

        $jalur = $this->berkasCsv->getRealPath();
        $tangan = fopen($jalur, 'r');

        $judul = fgetcsv($tangan);

        if ($judul === false) {
            $this->addError('berkasCsv', __('master.berkas_kosong'));
            fclose($tangan);

            return;
        }

        // Nama kolom dinormalkan supaya "Kode Toko", "kode_toko" dan
        // "KODE TOKO" sama-sama dikenali.
        $judul = array_map(
            fn ($k) => str_replace(' ', '_', mb_strtolower(trim((string) $k))),
            $judul,
        );

        $wilayahPerNama = Wilayah::pluck('id', 'nama')
            ->mapWithKeys(fn ($id, $nama) => [mb_strtolower($nama) => $id]);
        $wilayahPerKode = Wilayah::pluck('id', 'kode')
            ->mapWithKeys(fn ($id, $kode) => [mb_strtolower($kode) => $id]);

        $baru = 0;
        $diperbarui = 0;
        $dilewati = [];
        $nomor = 1;

        while (($baris = fgetcsv($tangan)) !== false) {
            $nomor++;

            if (count(array_filter($baris, fn ($n) => trim((string) $n) !== '')) === 0) {
                continue;
            }

            $data = array_combine($judul, array_pad(array_slice($baris, 0, count($judul)), count($judul), null));

            $kode = trim((string) ($data['kode'] ?? $data['kode_toko'] ?? ''));
            $nama = trim((string) ($data['nama'] ?? $data['nama_toko'] ?? ''));
            $alamat = trim((string) ($data['alamat'] ?? ''));
            $wilayahTeks = mb_strtolower(trim((string) ($data['wilayah'] ?? '')));

            if ($nama === '' || $alamat === '') {
                $dilewati[] = __('master.lewat_kosong', ['nomor' => $nomor]);

                continue;
            }

            $wilayahId = $wilayahPerNama[$wilayahTeks] ?? $wilayahPerKode[$wilayahTeks] ?? null;

            if ($wilayahId === null) {
                $dilewati[] = __('master.lewat_wilayah', ['nomor' => $nomor, 'wilayah' => $wilayahTeks]);

                continue;
            }

            $lat = $this->angkaAtauNull($data['latitude'] ?? $data['lat'] ?? null);
            $lng = $this->angkaAtauNull($data['longitude'] ?? $data['lng'] ?? $data['lon'] ?? null);
            $punyaTitik = $lat !== null && $lng !== null;

            $kode = $kode !== '' ? $kode : $this->kodeBerikutnya();
            $adaSebelumnya = Toko::where('kode', $kode)->exists();

            Toko::updateOrCreate(['kode' => $kode], [
                'nama' => $nama,
                'alamat' => $alamat,
                'wilayah_id' => $wilayahId,
                'kelurahan' => $this->teksAtauNull($data['kelurahan'] ?? null),
                'kecamatan' => $this->teksAtauNull($data['kecamatan'] ?? null),
                'kota' => $this->teksAtauNull($data['kota'] ?? null),
                'kode_pos' => $this->teksAtauNull($data['kode_pos'] ?? null),
                'telepon' => $this->teksAtauNull($data['telepon'] ?? null),
                'nama_pemilik' => $this->teksAtauNull($data['nama_pemilik'] ?? $data['pemilik'] ?? null),
                'latitude' => $lat,
                'longitude' => $lng,
                'sumber_koordinat' => $punyaTitik ? 'manual' : 'belum',
                'geocoded_at' => $punyaTitik ? now() : null,
                'aktif' => true,
            ]);

            $adaSebelumnya ? $diperbarui++ : $baru++;
        }

        fclose($tangan);

        $this->hasilImpor = [
            'baru' => $baru,
            'diperbarui' => $diperbarui,
            'dilewati' => $dilewati,
        ];

        $this->berkasCsv = null;
        unset($this->tokos, $this->jumlahTanpaKoordinat);
    }

    public function unduhContohCsv()
    {
        $wilayah = Wilayah::value('nama') ?? 'Jakarta Pusat';

        return response()->streamDownload(function () use ($wilayah): void {
            $keluaran = fopen('php://output', 'w');

            fputcsv($keluaran, ['kode', 'nama', 'alamat', 'wilayah', 'kelurahan', 'kecamatan', 'kota', 'kode_pos', 'telepon', 'pemilik', 'latitude', 'longitude']);
            fputcsv($keluaran, ['TK-0001', 'Toko Contoh Jaya', 'Jl. Merdeka No. 10', $wilayah, 'Gambir', 'Gambir', 'Jakarta Pusat', '10110', '081234567890', 'Budi', '-6.1751', '106.8272']);
            fputcsv($keluaran, ['', 'Toko Tanpa Koordinat', 'Jl. Sudirman No. 5', $wilayah, '', '', 'Jakarta Pusat', '', '', '', '', '']);

            fclose($keluaran);
        }, 'contoh-import-toko.csv', ['Content-Type' => 'text/csv']);
    }

    private function angkaAtauNull($nilai): ?float
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' || ! is_numeric($nilai) ? null : (float) $nilai;
    }

    private function teksAtauNull($nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }

    /**
     * Nomor urut berikutnya untuk kode toko. Karena nomornya diisi nol di
     * depan, urutan abjad sama dengan urutan angka, jadi cukup ambil yang
     * terbesar lalu potong awalannya — tanpa fungsi SQL yang khas satu mesin
     * basis data saja.
     */
    private function kodeBerikutnya(): string
    {
        $terakhir = Toko::query()
            ->where('kode', 'like', 'TK-%')
            ->orderByDesc('kode')
            ->value('kode');

        $nomor = $terakhir === null ? 0 : (int) substr($terakhir, 3);

        return sprintf('TK-%04d', $nomor + 1);
    }

    public function render()
    {
        return view('livewire.master.daftar-toko')->title(__('master.judul_toko'));
    }
}
