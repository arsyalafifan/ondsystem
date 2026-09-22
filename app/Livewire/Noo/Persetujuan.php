<?php

namespace App\Livewire\Noo;

use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Livewire\Noo\Concerns\PunyaFormNoo;
use App\Models\Noo;
use App\Models\Toko;
use App\Services\Noo\NooService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Antrean persetujuan calon mitra baru.
 *
 * Admin boleh mengoreksi seluruh data sebelum menyetujui — sales mendata di
 * lapangan sambil berdiri di depan toko, salah ketik itu wajar, dan menolak
 * pengajuan hanya karena satu huruf akan membuang perjalanan yang sudah
 * dilakukan. Setiap koreksi dicatat (`diubah_admin_*`) supaya perbedaan
 * antara data lapangan dan data yang akhirnya disetujui tidak pernah jadi
 * perdebatan tanpa jejak.
 *
 * Sebelum menyetujui, admin diperingatkan kalau titiknya berdiri kurang dari
 * 200 m dari toko aktif yang sudah ada. Itu PERINGATAN, bukan larangan: dua
 * toko bersebelahan memang mungkin, dan yang tahu duduk perkaranya adalah
 * admin — aplikasi hanya memastikan ia tidak menyetujuinya tanpa sadar.
 */
class Persetujuan extends Component
{
    use MembutuhkanDepotTerkunci, PunyaFormNoo, WithPagination;

    public ?int $nooDipilih = null;

    public string $alasanTolak = '';

    public bool $formTolakTerbuka = false;

    /** Admin sudah melihat peringatan jaraknya dan tetap memilih lanjut. */
    public bool $peringatanDikonfirmasi = false;

    public bool $peringatanTerbuka = false;

    public function mount(): void
    {
        $this->pastikanDepotTerkunci();
    }

    /** @return LengthAwarePaginator<int, Noo> */
    #[Computed]
    public function antrean(): LengthAwarePaginator
    {
        return Noo::query()
            ->menungguPersetujuan()
            ->with(['paket:id,nama', 'wilayah:id,nama', 'pengaju:id,name'])
            ->oldest('diajukan_at')
            ->paginate(15);
    }

    #[Computed]
    public function noo(): ?Noo
    {
        return $this->nooDipilih === null
            ? null
            : Noo::query()
                ->menungguPersetujuan()
                ->with(['paket.items.produk:id,nama', 'wilayah:id,nama', 'pengaju:id,name', 'fotos'])
                ->find($this->nooDipilih);
    }

    /**
     * Toko aktif terdekat dari titik yang SEDANG ADA DI FORMULIR — bukan dari
     * titik yang tersimpan, karena admin boleh menggesernya sebelum
     * menyetujui dan peringatannya harus ikut titik yang baru.
     *
     * @return array{toko: Toko, jarak: int}|null
     */
    #[Computed]
    public function peringatanJarak(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return app(NooService::class)->tokoAktifTerdekat($this->latitude, $this->longitude);
    }

    public function pilih(int $id): void
    {
        $noo = Noo::menungguPersetujuan()->find($id);

        if ($noo === null) {
            return;
        }

        $this->nooDipilih = $noo->id;
        $this->isiFormDari($noo);
        $this->reset(['alasanTolak', 'formTolakTerbuka', 'peringatanDikonfirmasi', 'peringatanTerbuka']);
    }

    public function tutup(): void
    {
        $this->nooDipilih = null;
        $this->kosongkanForm();
        $this->reset(['alasanTolak', 'formTolakTerbuka', 'peringatanDikonfirmasi', 'peringatanTerbuka']);
    }

    /**
     * Menyimpan koreksi admin tanpa menyetujui — supaya data yang sudah
     * dibetulkan tidak hilang kalau keputusannya ditunda dulu.
     */
    public function simpanPerubahan(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $noo = $this->noo;

        if ($noo === null) {
            return;
        }

        $this->terapkanPerubahan($noo, $this->validasiForm());

        unset($this->noo, $this->antrean);

        $this->dispatch('notifikasi', pesan: __('noo.notif_perubahan_disimpan'));
    }

    public function setujui(NooService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $noo = $this->noo;

        if ($noo === null) {
            return;
        }

        $data = $this->validasiForm();
        $this->terapkanPerubahan($noo, $data);

        // Dihitung ulang setelah koreksi tersimpan: titik yang dipakai
        // memutuskan harus titik yang benar-benar akan disimpan.
        unset($this->peringatanJarak);

        if ($this->peringatanJarak !== null && ! $this->peringatanDikonfirmasi) {
            $this->peringatanTerbuka = true;

            return;
        }

        try {
            $toko = $service->setujui($noo->fresh(), auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $kode = $noo->kode;
        $this->tutup();
        unset($this->antrean);

        $this->dispatch('notifikasi', pesan: __('noo.notif_disetujui', [
            'kode' => $kode,
            'toko' => $toko->kode,
        ]));
    }

    /** Lanjut menyetujui meski jaraknya terlalu dekat dengan toko aktif. */
    public function setujuiTetap(NooService $service): void
    {
        $this->peringatanDikonfirmasi = true;
        $this->peringatanTerbuka = false;

        $this->setujui($service);
    }

    public function tolak(NooService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $noo = $this->noo;

        if ($noo === null) {
            return;
        }

        $this->validate(
            ['alasanTolak' => 'required|string|max:500'],
            ['alasanTolak.required' => __('noo.galat_alasan_tolak_wajib')],
        );

        try {
            $service->tolak($noo, auth()->user(), $this->alasanTolak);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $kode = $noo->kode;
        $this->tutup();
        unset($this->antrean);

        $this->dispatch('notifikasi', pesan: __('noo.notif_ditolak', ['kode' => $kode]), jenis: 'info');
    }

    /**
     * Menulis koreksi admin, dan hanya menandai `diubah_admin_*` kalau
     * memang ada yang berubah — membuka lalu menutup formulir tanpa
     * mengubah apa pun tidak boleh meninggalkan jejak seolah-olah datanya
     * disunting.
     *
     * @param  array<string, mixed>  $data
     */
    private function terapkanPerubahan(Noo $noo, array $data): void
    {
        $berubah = collect($data)->contains(fn (mixed $nilai, string $kolom): bool => $noo->getAttribute($kolom) != $nilai);

        $noo->update($berubah ? [
            ...$data,
            'diubah_admin_oleh' => auth()->id(),
            'diubah_admin_at' => now(),
        ] : $data);
    }

    public function render()
    {
        return view('livewire.noo.persetujuan')->title(__('noo.judul_persetujuan'));
    }
}
