<?php

namespace App\Livewire\Toko;

use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\PenugasanToko;
use App\Models\Toko;
use App\Support\DepotContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Layar bagi sales melengkapi profil toko tanggungannya (data pemilik,
 * kontak, alamat administratif) — admin/superadmin juga bisa mengaksesnya
 * untuk toko mana pun, tidak dibatasi tanggungan siapa pun.
 *
 * Cara memilih tokonya sengaja meniru pencarian toko di Input Pesanan
 * (`BuatPesanan::hasilCari()`) — ketik nama/kode/nomor aset, bukan tokonya
 * yang dibuat baru di sini. Koordinat, nama, dan kode toko TIDAK PERNAH
 * bisa diubah lewat layar ini — hanya field profil yang boleh disunting,
 * dan payload simpan() tidak pernah menyertakan ketiganya sama sekali,
 * apa pun yang klien coba kirim.
 */
class LengkapiData extends Component
{
    use MembutuhkanDepotTerkunci;

    public string $cari = '';

    public ?int $tokoId = null;

    // --- Field yang boleh disunting ---
    public string $namaPemilik = '';

    public string $nikPemilik = '';

    public string $alamat = '';

    public string $assetId = '';

    public string $telepon = '';

    public string $kecamatan = '';

    public string $kota = '';

    public string $provinsi = '';

    /**
     * Toko yang boleh disentuh dari layar ini: sales dibatasi ke
     * tanggungannya sendiri (`PenugasanToko`), admin/superadmin tidak
     * dibatasi sama sekali. Dipakai BERSAMA oleh pencarian dan simpan() —
     * simpan() memakainya ulang untuk memastikan toko yang disimpan
     * memang benar-benar toko yang boleh disentuh pengguna ini, tidak
     * sekadar mempercayai $tokoId dari klien.
     */
    private function tokoBolehDisentuh(): Builder
    {
        $query = Toko::query()->aktif();

        if (auth()->user()->isSales()) {
            $query->whereIn('id', PenugasanToko::query()->where('sales_id', auth()->id())->pluck('toko_id'));
        }

        return $query;
    }

    public function pilihToko(int $id): void
    {
        $toko = $this->tokoBolehDisentuh()->find($id);

        if ($toko === null) {
            return;
        }

        $this->tokoId = $toko->id;
        $this->cari = '';
        $this->namaPemilik = (string) $toko->nama_pemilik;
        $this->nikPemilik = (string) $toko->nik_pemilik;
        $this->alamat = (string) $toko->alamat;
        $this->assetId = (string) $toko->asset_id;
        $this->telepon = (string) $toko->telepon;
        $this->kecamatan = (string) $toko->kecamatan;
        $this->kota = (string) $toko->kota;
        $this->provinsi = (string) $toko->provinsi;
        $this->resetValidation();
    }

    public function batalPilihToko(): void
    {
        $this->reset([
            'tokoId', 'namaPemilik', 'nikPemilik', 'alamat', 'assetId',
            'telepon', 'kecamatan', 'kota', 'provinsi',
        ]);
    }

    /**
     * Pencarian toko: nama, kode, atau nomor aset freezer — pola yang sama
     * dengan `BuatPesanan::hasilCari()`, disaring dulu lewat
     * tokoBolehDisentuh() sebelum kata kuncinya diterapkan.
     *
     * @return Collection<int, Toko>
     */
    #[Computed]
    public function hasilCari(): Collection
    {
        if (mb_strlen(trim($this->cari)) < 2) {
            return collect();
        }

        $kata = trim($this->cari);
        $aset = mb_strtoupper(preg_replace('/\s+/', '', $kata) ?? '');

        return $this->tokoBolehDisentuh()
            ->with('wilayah:id,nama')
            ->where(fn ($q) => $q
                ->where('nama', 'like', "%{$kata}%")
                ->orWhere('kode', 'like', "%{$kata}%")
                ->orWhere('asset_id', 'like', "%{$aset}%"))
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

    public function simpan(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        if ($this->tokoId === null) {
            $this->addError('tokoId', __('toko.pilih_toko_dulu'));

            return;
        }

        // Diambil ulang dari server, bukan mempercayai $this->tokoId begitu
        // saja — kalau ternyata bukan/tidak lagi jadi toko yang boleh
        // disentuh pengguna ini (mis. tanggungan sales sudah dialihkan di
        // tengah jalan), tolak di sini, bukan cuma disembunyikan di
        // tampilan.
        $toko = $this->tokoBolehDisentuh()->find($this->tokoId);

        if ($toko === null) {
            $this->addError('tokoId', __('toko.galat_toko_tidak_valid'));

            return;
        }

        // nik_pemilik & telepon unik PER DEPOT — pemilik yang sama (atau
        // kebetulan NIK/nomor sama) boleh terdaftar di toko depot lain,
        // itu memang toko yang sepenuhnya berbeda. asset_id TETAP unik
        // GLOBAL: nomor fisik stiker QR freezer, satu barang fisik tidak
        // mungkin ada di 2 depot sekaligus.
        $depotId = DepotContext::currentOrFail()->id;

        $data = $this->validate([
            'namaPemilik' => 'required|string|max:255',
            'nikPemilik' => [
                'required', 'digits:16',
                Rule::unique('tokos', 'nik_pemilik')->ignore($toko->id)->where('depot_id', $depotId),
            ],
            'alamat' => 'required|string',
            'assetId' => ['required', 'string', 'max:40', Rule::unique('tokos', 'asset_id')->ignore($toko->id)],
            'telepon' => [
                'required', 'string', 'max:30',
                Rule::unique('tokos', 'telepon')->ignore($toko->id)->where('depot_id', $depotId),
            ],
            // Ketiganya boleh kosong — tidak memengaruhi rute pengantaran
            // (yang dipakai cuma titik koordinat) maupun transaksi lain,
            // beda dari kelima field di atas yang benar-benar dibutuhkan
            // (identitas pemilik, kontak, pengenal freezer).
            'kecamatan' => 'nullable|string|max:255',
            'kota' => 'nullable|string|max:255',
            'provinsi' => 'nullable|string|max:255',
        ], [
            'nikPemilik.digits' => __('master.nik_tidak_valid'),
            'nikPemilik.unique' => __('toko.galat_nik_dipakai'),
            'assetId.unique' => __('toko.galat_freezer_dipakai'),
            'telepon.unique' => __('toko.galat_hp_dipakai'),
        ], [
            'namaPemilik' => __('master.atr_nama_pemilik'),
            'nikPemilik' => __('master.atr_nik_pemilik'),
            'alamat' => __('master.atr_alamat'),
            'assetId' => __('master.atr_asset_id'),
            'telepon' => __('master.atr_telepon'),
            'kecamatan' => __('master.atr_kecamatan'),
            'kota' => __('master.atr_kota'),
            'provinsi' => __('master.atr_provinsi'),
        ]);

        // Koordinat, nama, dan kode SENGAJA tidak pernah masuk daftar ini —
        // tidak ada input untuknya di layar ini, dan seandainya pun klien
        // mencoba mengirim properti lain, update() di bawah cuma menulis
        // kolom yang disebut eksplisit di sini.
        $toko->update([
            'nama_pemilik' => $data['namaPemilik'],
            'nik_pemilik' => $data['nikPemilik'],
            'alamat' => $data['alamat'],
            // Dirapikan sama seperti Master Toko: huruf besar tanpa spasi,
            // supaya tetap cocok dengan hasil pemindaian QR freezer.
            'asset_id' => mb_strtoupper(preg_replace('/\s+/', '', $data['assetId'])),
            'telepon' => $data['telepon'],
            'kecamatan' => $data['kecamatan'] ?: null,
            'kota' => $data['kota'] ?: null,
            'provinsi' => $data['provinsi'] ?: null,
        ]);

        $this->dispatch('notifikasi', pesan: __('toko.notif_tersimpan', ['nama' => $toko->nama]));

        $this->batalPilihToko();
        unset($this->hasilCari);
    }

    public function render()
    {
        return view('livewire.toko.lengkapi-data')->title(__('toko.judul'));
    }
}
