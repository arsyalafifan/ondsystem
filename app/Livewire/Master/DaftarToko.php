<?php

namespace App\Livewire\Master;

use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Models\Wilayah;
use App\Services\Peta\NominatimGeocoder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

    #[Url]
    public bool $tanpaWilayah = false;

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

    public string $nikPemilik = '';

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

    /**
     * Berkas besar (ribuan baris) diproses bertahap lewat beberapa request
     * kecil, bukan sekaligus dalam satu request — supaya tidak kena batas
     * waktu eksekusi PHP/web-server di production. Baris-baris yang sudah
     * dinormalkan disimpan sementara di disk lokal antar-tahap, karena
     * menyimpannya di properti Livewire akan membengkakkan payload yang
     * dikirim bolak-balik setiap tahap.
     */
    public bool $imporBerjalan = false;

    public ?string $imporToken = null;

    public int $imporOffset = 0;

    public int $imporTotal = 0;

    public int $imporBaru = 0;

    public int $imporDiperbarui = 0;

    /** @var array<int, string> */
    public array $imporDilewati = [];

    /** @var array<int, string> */
    public array $imporCatatan = [];

    private int $ukuranBatchImpor = 250;

    public bool $sedangGeocodeMassal = false;

    public function updated(string $kolom): void
    {
        if (in_array($kolom, ['cari', 'filterWilayah', 'tanpaKoordinat', 'tanpaWilayah'], true)) {
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
            ->when($this->tanpaWilayah, fn ($q) => $q->tanpaWilayah())
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
    public function jumlahTanpaWilayah(): int
    {
        return Toko::aktif()->tanpaWilayah()->count();
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
        $this->nikPemilik = $toko->nik_pemilik ?? '';
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
            'kelurahan', 'kecamatan', 'kota', 'kodePos', 'telepon', 'namaPemilik', 'nikPemilik',
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
            'nikPemilik' => 'nullable|digits:16',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ], [
            'nikPemilik.digits' => __('master.nik_tidak_valid'),
        ], [
            'kode' => __('master.atr_kode_toko'),
            'assetId' => __('master.atr_asset_id'),
            'nama' => __('master.atr_nama_toko'),
            'wilayahId' => __('master.atr_wilayah'),
            'kodePos' => __('master.atr_kode_pos'),
            'namaPemilik' => __('master.atr_nama_pemilik'),
            'nikPemilik' => __('master.atr_nik_pemilik'),
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
            'nik_pemilik' => $this->nikPemilik ?: null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'sumber_koordinat' => $this->latitude === null ? 'belum' : $this->sumberKoordinat,
            'geocoded_at' => $this->latitude === null ? null : now(),
            'aktif' => $this->aktif,
        ]);

        $pesan = $this->tokoId === null ? __('master.toko_tersimpan') : __('master.toko_diperbarui');

        $this->tutupForm();
        unset($this->tokos, $this->jumlahTanpaKoordinat, $this->jumlahTanpaWilayah);

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
    // Impor CSV / Excel
    // ------------------------------------------------------------------

    /**
     * Membaca dan menormalkan seluruh berkas sekali di awal, lalu
     * menyimpannya sementara di disk supaya tiap tahap tinggal membaca
     * potongan barisnya — bukan mengurai ulang berkas Excel-nya setiap kali.
     */
    public function mulaiImporCsv(): void
    {
        $this->validate([
            'berkasCsv' => 'required|file|mimes:csv,txt,xlsx,xls|max:20480',
        ], [
            'berkasCsv.required' => __('master.pilih_csv_dulu'),
            'berkasCsv.mimes' => __('master.harus_csv'),
        ]);

        $jalur = $this->berkasCsv->getRealPath();
        $ekstensi = mb_strtolower((string) $this->berkasCsv->getClientOriginalExtension());

        $semuaBaris = in_array($ekstensi, ['xlsx', 'xls'], true)
            ? $this->bacaBarisExcel($jalur)
            : $this->bacaBarisCsv($jalur);

        $judul = array_shift($semuaBaris);

        if ($judul === null) {
            $this->addError('berkasCsv', __('master.berkas_kosong'));

            return;
        }

        // Nama kolom dinormalkan supaya "Kode Toko", "kode_toko" dan
        // "KODE TOKO" sama-sama dikenali.
        $judul = array_map(
            fn ($k) => str_replace(' ', '_', mb_strtolower(trim((string) $k))),
            $judul,
        );

        // Dinormalkan sekali di sini supaya tiap tahap tinggal memakai teks
        // biasa, tidak perlu menangani sel Excel mentah berulang kali.
        $barisNormal = array_values(array_map(
            fn ($baris) => $this->normalisasiBaris($baris),
            $semuaBaris,
        ));

        if ($barisNormal === []) {
            $this->addError('berkasCsv', __('master.berkas_kosong'));

            return;
        }

        $token = (string) Str::uuid();
        Storage::disk('local')->put(
            "impor-toko/{$token}.json",
            json_encode(['judul' => $judul, 'baris' => $barisNormal]),
        );

        $this->imporToken = $token;
        $this->imporOffset = 0;
        $this->imporTotal = count($barisNormal);
        $this->imporBaru = 0;
        $this->imporDiperbarui = 0;
        $this->imporDilewati = [];
        $this->imporCatatan = [];
        $this->hasilImpor = null;
        $this->berkasCsv = null;
        $this->imporBerjalan = true;
    }

    /**
     * Dipanggil berulang oleh wire:poll di tampilan selama $imporBerjalan
     * masih true, tiap kali memproses satu batch kecil. Dengan begitu satu
     * berkas berisi ribuan baris tetap selesai lewat banyak request pendek,
     * bukan satu request raksasa yang gampang kena batas waktu di
     * production.
     */
    public function lanjutkanImporCsv(): void
    {
        if (! $this->imporBerjalan || $this->imporToken === null) {
            return;
        }

        $jalurBerkas = "impor-toko/{$this->imporToken}.json";

        if (! Storage::disk('local')->exists($jalurBerkas)) {
            $this->selesaikanImporCsv();

            return;
        }

        $tersimpan = json_decode(Storage::disk('local')->get($jalurBerkas), true);
        $judul = $tersimpan['judul'];
        $batch = array_slice($tersimpan['baris'], $this->imporOffset, $this->ukuranBatchImpor);

        $wilayahPerNama = Wilayah::pluck('id', 'nama')
            ->mapWithKeys(fn ($id, $nama) => [mb_strtolower($nama) => $id]);
        $wilayahPerKode = Wilayah::pluck('id', 'kode')
            ->mapWithKeys(fn ($id, $kode) => [mb_strtolower($kode) => $id]);

        // Toko yang sudah ada dan status pesanannya diambil sekaligus di
        // depan tiap batch (bukan satu query per baris) — berkas
        // beratus/beribu baris sebelumnya bisa memicu ribuan query kecil dan
        // membuat impor melebihi batas waktu di production. Hanya kolom yang
        // dipakai untuk pencocokan yang diambil, supaya ringan walau
        // toko-nya banyak.
        $tokoRingkas = Toko::query()->select(['id', 'kode', 'asset_id'])->get();
        $tokoPerKode = $tokoRingkas->keyBy('kode');
        $tokoPerAssetId = $tokoRingkas->whereNotNull('asset_id')->keyBy('asset_id');

        $tokoIdPesananAktif = Pesanan::query()
            ->whereIn('status', StatusPesanan::aktif())
            ->distinct()
            ->pluck('toko_id')
            ->flip();

        // Nomor urut kode otomatis dihitung ulang tiap batch dari data
        // terbaru (batch sebelumnya sudah tersimpan ke basis data), lalu
        // dinaikkan di memori selama batch ini berjalan.
        $kodeTerakhirAngka = (int) substr((string) (
            Toko::query()->where('kode', 'like', 'TK-%')->orderByDesc('kode')->value('kode') ?? 'TK-0000'
        ), 3);

        $baru = 0;
        $diperbarui = 0;
        $dilewati = [];
        $catatan = [];
        $nomor = $this->imporOffset + 1;

        DB::transaction(function () use (
            $batch, $judul, $wilayahPerNama, $wilayahPerKode,
            &$tokoPerKode, &$tokoPerAssetId, $tokoIdPesananAktif, &$kodeTerakhirAngka,
            &$baru, &$diperbarui, &$dilewati, &$catatan, &$nomor,
        ): void {
            foreach ($batch as $baris) {
                $nomor++;

                if (count(array_filter($baris, fn ($n) => trim((string) $n) !== '')) === 0) {
                    continue;
                }

                $data = array_combine($judul, array_pad(array_slice($baris, 0, count($judul)), count($judul), null));

                $kode = trim((string) ($data['kode'] ?? $data['kode_toko'] ?? ''));
                $nama = trim((string) ($data['nama'] ?? $data['nama_toko'] ?? ''));
                $alamat = trim((string) ($data['alamat'] ?? ''));
                $wilayahTeks = mb_strtolower(trim((string) ($data['wilayah'] ?? '')));

                $assetIdMentah = trim((string) ($data['asset_id'] ?? $data['kode_aset'] ?? $data['no_aset'] ?? ''));
                $assetId = $assetIdMentah === '' ? null : mb_strtoupper(preg_replace('/\s+/', '', $assetIdMentah));

                if ($nama === '' || $alamat === '') {
                    $dilewati[] = __('master.lewat_kosong', ['nomor' => $nomor]);

                    continue;
                }

                // Wilayah boleh kosong — tokonya tetap dibuat/diperbarui, tinggal
                // dilengkapi belakangan lewat upload susulan atau formulir edit.
                // Baris hanya dilewati kalau wilayahnya DIISI tapi tidak dikenal,
                // karena itu biasanya salah ketik yang perlu diperbaiki dulu.
                $wilayahId = null;

                if ($wilayahTeks !== '') {
                    $wilayahId = $wilayahPerNama[$wilayahTeks] ?? $wilayahPerKode[$wilayahTeks] ?? null;

                    if ($wilayahId === null) {
                        $dilewati[] = __('master.lewat_wilayah', ['nomor' => $nomor, 'wilayah' => $wilayahTeks]);

                        continue;
                    }
                }

                $lat = $this->angkaAtauNull($data['latitude'] ?? $data['lat'] ?? null);
                $lng = $this->angkaAtauNull($data['longitude'] ?? $data['lng'] ?? $data['lon'] ?? null);

                // Kalau kolom latitude/longitude di Excel tidak berformat Teks,
                // sebagian pengaturan lokal membaca titik desimalnya sebagai
                // pemisah ribuan dan mengubah koordinat jadi angka raksasa di
                // luar jangkauan bumi. Baris tetap diproses, koordinatnya saja
                // yang diabaikan dan dicatat supaya kelihatan alasannya.
                $koordinatTidakValid = ($lat !== null && ($lat < -90 || $lat > 90))
                    || ($lng !== null && ($lng < -180 || $lng > 180));

                if ($koordinatTidakValid) {
                    $lat = null;
                    $lng = null;
                }

                $punyaTitik = $lat !== null && $lng !== null;

                // Toko yang sama dikenali lewat nomor aset (paling andal, karena
                // tercetak di badan freezer) atau kode toko, supaya baris yang
                // mengacu ke toko lama memperbarui datanya, bukan menggandakan.
                // Dicocokkan dari peta yang sudah diambil di depan, bukan query
                // per baris.
                $tokoLama = $assetId !== null ? ($tokoPerAssetId[$assetId] ?? null) : null;
                $tokoLama ??= $kode !== '' ? ($tokoPerKode[$kode] ?? null) : null;

                if ($tokoLama !== null) {
                    $kodeAkhir = $tokoLama->kode;
                } elseif ($kode !== '') {
                    $kodeAkhir = $kode;
                } else {
                    $kodeTerakhirAngka++;
                    $kodeAkhir = sprintf('TK-%04d', $kodeTerakhirAngka);
                }

                $adaSebelumnya = $tokoLama !== null;

                // Toko yang masih punya pesanan berjalan tidak boleh diseret
                // wilayah/nomor asetnya lewat impor massal — itu bisa mengacaukan
                // routing atau pengenalan QR di tengah transaksi. Baris tetap
                // diproses, hanya kolom kritisnya yang dikunci dan dicatat.
                $pesananAktifAda = $adaSebelumnya && isset($tokoIdPesananAktif[$tokoLama->id]);

                $dataSimpan = [
                    'nama' => $nama,
                    'alamat' => $alamat,
                    'aktif' => true,
                ];

                // Kolom opsional (termasuk nomor aset dan wilayah) hanya ditimpa
                // kalau berkasnya memang mengisi nilainya. Toko sering dilengkapi
                // bertahap lewat beberapa kali upload — kolom yang masih kosong
                // di berkas terbaru tidak boleh menghapus data yang sudah
                // tersimpan dari upload atau input sebelumnya.
                $kolomOpsional = [
                    'wilayah_id' => $wilayahId,
                    'asset_id' => $assetId,
                    'kelurahan' => $this->teksAtauNull($data['kelurahan'] ?? null),
                    'kecamatan' => $this->teksAtauNull($data['kecamatan'] ?? null),
                    'kota' => $this->teksAtauNull($data['kota'] ?? null),
                    'kode_pos' => $this->teksAtauNull($data['kode_pos'] ?? null),
                    'telepon' => $this->teksAtauNull($data['telepon'] ?? null),
                    'nama_pemilik' => $this->teksAtauNull($data['nama_pemilik'] ?? $data['pemilik'] ?? null),
                    'nik_pemilik' => $this->teksAtauNull($data['nik_pemilik'] ?? $data['nik'] ?? null),
                ];

                $kolomTerkunci = [];

                if ($pesananAktifAda) {
                    foreach (['wilayah_id' => 'master.kolom_wilayah', 'asset_id' => 'master.kolom_asset_id'] as $kolom => $labelKey) {
                        if ($kolomOpsional[$kolom] !== null) {
                            $kolomTerkunci[] = __($labelKey);
                            unset($kolomOpsional[$kolom]);
                        }
                    }
                }

                foreach ($kolomOpsional as $kolom => $nilai) {
                    if ($nilai !== null) {
                        $dataSimpan[$kolom] = $nilai;
                    }
                }

                // Koordinat sama-sama diperlakukan begitu: hanya ditimpa saat
                // baris punya lat/lng lengkap. Baris yang belum punya koordinat
                // tidak menghapus titik yang sudah digeocode atau ditaruh manual.
                if ($punyaTitik) {
                    if ($pesananAktifAda) {
                        $kolomTerkunci[] = __('master.kolom_koordinat');
                    } else {
                        $dataSimpan['latitude'] = $lat;
                        $dataSimpan['longitude'] = $lng;
                        $dataSimpan['sumber_koordinat'] = 'manual';
                        $dataSimpan['geocoded_at'] = now();
                    }
                }

                // updateOrCreate() diam-diam melakukan query pengecekan sendiri;
                // di sini toko lama sudah diketahui dari peta, jadi langsung
                // create/save supaya tidak ada query tersembunyi per baris.
                if ($tokoLama !== null) {
                    $tokoLama->fill($dataSimpan);
                    $tokoLama->save();
                    $tokoTersimpan = $tokoLama;
                } else {
                    $dataSimpan['kode'] = $kodeAkhir;
                    $tokoTersimpan = Toko::create($dataSimpan);
                }

                $tokoPerKode[$tokoTersimpan->kode] = $tokoTersimpan;

                if ($tokoTersimpan->asset_id !== null) {
                    $tokoPerAssetId[$tokoTersimpan->asset_id] = $tokoTersimpan;
                }

                if ($kolomTerkunci !== []) {
                    $catatan[] = __('master.catatan_kolom_terkunci', [
                        'nomor' => $nomor,
                        'kode' => $kodeAkhir,
                        'kolom' => implode(', ', $kolomTerkunci),
                    ]);
                }

                if ($koordinatTidakValid) {
                    $catatan[] = __('master.catatan_koordinat_tidak_valid', [
                        'nomor' => $nomor,
                        'kode' => $kodeAkhir,
                    ]);
                }

                $adaSebelumnya ? $diperbarui++ : $baru++;
            }
        });

        $this->imporBaru += $baru;
        $this->imporDiperbarui += $diperbarui;
        $this->imporDilewati = [...$this->imporDilewati, ...$dilewati];
        $this->imporCatatan = [...$this->imporCatatan, ...$catatan];
        $this->imporOffset += count($batch);

        if ($this->imporOffset >= $this->imporTotal) {
            $this->selesaikanImporCsv();
        }
    }

    private function selesaikanImporCsv(): void
    {
        if ($this->imporToken !== null) {
            Storage::disk('local')->delete("impor-toko/{$this->imporToken}.json");
        }

        $this->hasilImpor = [
            'baru' => $this->imporBaru,
            'diperbarui' => $this->imporDiperbarui,
            'dilewati' => $this->imporDilewati,
            'catatan' => $this->imporCatatan,
        ];

        $this->imporBerjalan = false;
        $this->imporToken = null;
        unset($this->tokos, $this->jumlahTanpaKoordinat, $this->jumlahTanpaWilayah);
    }

    /** Membatalkan impor yang sedang berjalan, misalnya kalau admin menutup modal di tengah jalan. */
    public function batalkanImporCsv(): void
    {
        if ($this->imporToken !== null) {
            Storage::disk('local')->delete("impor-toko/{$this->imporToken}.json");
        }

        $this->reset(['imporBerjalan', 'imporToken', 'imporOffset', 'imporTotal', 'imporBaru', 'imporDiperbarui', 'imporDilewati', 'imporCatatan']);
    }

    /** @return array<int, array<int, mixed>> */
    private function bacaBarisCsv(string $jalur): array
    {
        $tangan = fopen($jalur, 'r');
        $baris = [];

        while (($satu = fgetcsv($tangan)) !== false) {
            $baris[] = $satu;
        }

        fclose($tangan);

        return $baris;
    }

    /** @return array<int, array<int, mixed>> */
    private function bacaBarisExcel(string $jalur): array
    {
        return IOFactory::load($jalur)->getSheet(0)->toArray(null, true, true, false);
    }

    /**
     * Sel angka dari Excel (kode pos, telepon, NIK) diratakan jadi teks biasa
     * tanpa notasi ilmiah, supaya nol di depan tidak dianggap hilang dan
     * angka besar tidak berubah jadi "8.1235E+10".
     *
     * @param  array<int, mixed>  $baris
     * @return array<int, string>
     */
    private function normalisasiBaris(array $baris): array
    {
        return array_map(function ($nilai) {
            if (is_float($nilai) && fmod($nilai, 1.0) === 0.0) {
                return number_format($nilai, 0, '', '');
            }

            return trim((string) $nilai);
        }, $baris);
    }

    public function unduhContohCsv()
    {
        $wilayah = Wilayah::value('nama') ?? 'Jakarta Pusat';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(
            ['kode', 'nama', 'pemilik', 'alamat', 'nik', 'telepon', 'latitude', 'longitude', 'wilayah', 'asset_id'],
            null, 'A1',
        );
        $sheet->fromArray(
            ['TK-0001', 'Toko Contoh Jaya', 'Budi', 'Jl. Merdeka No. 10', '3171012501900001', '081234567890', '-6.1751', '106.8272', $wilayah, 'IDNAH202528004381'],
            null, 'A2',
        );
        $sheet->fromArray(
            ['', 'Toko Tanpa Koordinat', '', 'Jl. Sudirman No. 5', '', '', '', '', $wilayah, ''],
            null, 'A3',
        );

        // Kolom yang rawan diubah otomatis oleh Excel dipaksa berformat teks:
        // nol di depan hilang atau notasi ilmiah untuk nik/telepon/asset_id,
        // dan yang lebih berbahaya lagi untuk latitude/longitude — pada
        // pengaturan lokal yang memakai titik sebagai pemisah ribuan, titik
        // desimal koordinat bisa terbaca keliru dan mengubahnya jadi angka
        // raksasa yang tidak masuk akal.
        foreach (['E', 'F', 'G', 'H', 'J'] as $kolom) {
            $sheet->getStyle("{$kolom}1:{$kolom}1000")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        foreach (range('A', 'J') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'contoh-import-toko.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
