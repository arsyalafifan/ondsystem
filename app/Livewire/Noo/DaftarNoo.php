<?php

namespace App\Livewire\Noo;

use App\Enums\JenisBuktiNoo;
use App\Enums\StatusNoo;
use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Livewire\Noo\Concerns\PunyaFormNoo;
use App\Models\Noo;
use App\Services\Noo\NooService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Pendataan calon mitra baru oleh sales di lapangan.
 *
 * Toko BELUM dibuat di sini — seluruh datanya menumpang di baris NOO sampai
 * admin menyetujui, supaya calon yang ditolak tidak pernah mengotori Master
 * Toko. Karena itu pula tidak ada IDN/nomor freezer di formulir ini: nomor
 * itu baru ada saat freezernya benar-benar terpasang, dan driver yang
 * mengisinya.
 *
 * Ketiga fotonya (KTP, kartu keluarga, tampak depan toko) ditahan di memori
 * komponen sebagai data URL sampai tombol simpan ditekan, persis seperti
 * bukti pengiriman di layar driver — satu pengajuan adalah satu kejadian,
 * jadi tidak ada gunanya menulis berkas untuk formulir yang mungkin
 * ditinggalkan setengah jalan.
 */
class DaftarNoo extends Component
{
    use MembutuhkanDepotTerkunci, PunyaFormNoo, WithPagination;

    #[Url(as: 'q')]
    public string $cari = '';

    #[Url]
    public string $filterStatus = '';

    public ?int $nooDilihat = null;

    public bool $formTerbuka = false;

    /** @var array<string, ?string> */
    public array $buktiFoto = [];

    public function mount(): void
    {
        $this->pastikanDepotTerkunci();
    }

    // ------------------------------------------------------------------
    // Daftar
    // ------------------------------------------------------------------

    /**
     * Sales hanya melihat pengajuannya sendiri; admin melihat semuanya.
     * Disaring di query, bukan cuma disembunyikan di tampilan.
     */
    private function dasarKueri(): Builder
    {
        return Noo::query()
            ->when(auth()->user()->isSales(), fn (Builder $q) => $q->where('diajukan_oleh', auth()->id()));
    }

    /** @return LengthAwarePaginator<int, Noo> */
    #[Computed]
    public function noos(): LengthAwarePaginator
    {
        return $this->dasarKueri()
            ->with(['paket:id,nama', 'wilayah:id,nama', 'pengaju:id,name', 'toko:id,kode,nama'])
            ->when($this->filterStatus !== '', fn (Builder $q) => $q->where('status', $this->filterStatus))
            ->when(trim($this->cari) !== '', function (Builder $q): void {
                $kata = trim($this->cari);

                $q->where(fn (Builder $s) => $s
                    ->where('kode', 'like', "%{$kata}%")
                    ->orWhere('nama', 'like', "%{$kata}%")
                    ->orWhere('nama_pemilik', 'like', "%{$kata}%"));
            })
            ->latest('diajukan_at')
            ->paginate(15);
    }

    #[Computed]
    public function detail(): ?Noo
    {
        return $this->nooDilihat === null
            ? null
            : $this->dasarKueri()
                ->with(['paket.items.produk:id,nama', 'wilayah:id,nama', 'pengaju:id,name', 'penyetuju:id,name', 'penolak:id,name', 'toko:id,kode,nama,asset_id,aktif', 'fotos'])
                ->find($this->nooDilihat);
    }

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    /** @return array<int, StatusNoo> */
    #[Computed]
    public function statusCases(): array
    {
        return StatusNoo::cases();
    }

    // ------------------------------------------------------------------
    // Formulir
    // ------------------------------------------------------------------

    public function buatBaru(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $this->kosongkanForm();
        $this->buktiFoto = [];
        $this->wilayahId = $this->wilayahs->first()?->id;
        $this->paketNooId = $this->paketTersedia->first()?->id;
        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->kosongkanForm();
        $this->buktiFoto = [];
        $this->formTerbuka = false;
    }

    public function terimaBuktiFoto(string $jenis, string $gambar): void
    {
        if (in_array(JenisBuktiNoo::tryFrom($jenis), JenisBuktiNoo::wajibSales(), true)) {
            $this->buktiFoto[$jenis] = $gambar;
        }
    }

    public function hapusBuktiFoto(string $jenis): void
    {
        $this->buktiFoto[$jenis] = null;
    }

    /** Ketiga foto wajib sales sudah terisi. */
    #[Computed]
    public function semuaBuktiLengkap(): bool
    {
        foreach (JenisBuktiNoo::wajibSales() as $jenis) {
            if (empty($this->buktiFoto[$jenis->value])) {
                return false;
            }
        }

        return true;
    }

    public function simpan(NooService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $data = $this->validasiForm();

        if (! $this->semuaBuktiLengkap) {
            $this->dispatch('notifikasi', pesan: __('noo.galat_bukti_belum_lengkap'), jenis: 'error');

            return;
        }

        try {
            $noo = $service->ajukan($data, $this->buktiFoto, auth()->user());
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->tutupForm();
        unset($this->noos);

        $this->dispatch('notifikasi', pesan: __('noo.notif_diajukan', ['kode' => $noo->kode]));
    }

    public function render()
    {
        return view('livewire.noo.daftar-noo')->title(__('noo.judul'));
    }
}
