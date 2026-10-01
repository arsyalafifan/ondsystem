<?php

namespace App\Livewire\TransferStok;

use App\Enums\StatusTransferStok;
use App\Livewire\Concerns\MembutuhkanDepotTerkunci;
use App\Models\Depot;
use App\Models\Produk;
use App\Models\TransferStok;
use App\Services\TransferStok\TransferStokService;
use App\Support\DepotContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Transfer stok antar gudang penyimpanan — kirim dari gudang yang sedang
 * terkunci, terima transfer yang ditujukan ke gudang yang sedang terkunci.
 *
 * Satu layar untuk dua arah sekaligus: TransferStok terlihat dari gudang
 * asal MAUPUN tujuan (lihat TransferStok::saringDepot()), jadi admin yang
 * depot aktifnya kebetulan tujuan sebuah transfer melihatnya di sini juga,
 * tanpa perlu berpindah layar.
 */
class DaftarTransferStok extends Component
{
    use MembutuhkanDepotTerkunci, WithFileUploads, WithPagination;

    #[Url(as: 'q')]
    public string $cari = '';

    #[Url]
    public string $filterStatus = '';

    #[Url(as: 'arah')]
    public string $filterArah = '';

    public ?int $dilihat = null;

    // --- Formulir kirim ---
    public bool $formKirimTerbuka = false;

    public ?int $depotTujuanId = null;

    public string $catatanKirim = '';

    /** @var array<int, array{produk_id: int|string, jumlah: int|string}> */
    public array $baris = [];

    // --- Terima ---
    public ?int $terimaId = null;

    /** @var array<int, int|string> jumlah diterima, dikunci pada id TransferStokItem */
    public array $jumlahTerima = [];

    /** @var array<int, bool> */
    public array $dicekTerima = [];

    public string $catatanTerima = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $fotoTerima = [];

    // --- Batalkan ---
    public ?int $batalkanId = null;

    public string $alasanBatal = '';

    public function mount(): void
    {
        $this->pastikanDepotTerkunci();
    }

    private function dasarKueri(): Builder
    {
        return TransferStok::query()
            ->when($this->filterArah === 'keluar', fn (Builder $q) => $q->where('depot_asal_id', DepotContext::currentOrFail()->id))
            ->when($this->filterArah === 'masuk', fn (Builder $q) => $q->where('depot_tujuan_id', DepotContext::currentOrFail()->id))
            ->when(trim($this->cari) !== '', fn (Builder $q) => $q->where('kode', 'like', '%'.trim($this->cari).'%'));
    }

    /** @return LengthAwarePaginator<int, TransferStok> */
    #[Computed]
    public function daftar(): LengthAwarePaginator
    {
        return $this->dasarKueri()
            ->with(['depotAsal:id,nama', 'depotTujuan:id,nama', 'pengirim:id,name', 'items'])
            ->when($this->filterStatus !== '', fn (Builder $q) => $q->where('status', $this->filterStatus))
            ->latest('dikirim_at')
            ->paginate(15);
    }

    #[Computed]
    public function ringkasan(): array
    {
        $hitung = $this->dasarKueri()->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return collect(StatusTransferStok::cases())
            ->mapWithKeys(fn (StatusTransferStok $s) => [$s->value => (int) ($hitung[$s->value] ?? 0)])
            ->all();
    }

    /** @return array<int, StatusTransferStok> */
    #[Computed]
    public function statusCases(): array
    {
        return StatusTransferStok::cases();
    }

    #[Computed]
    public function detail(): ?TransferStok
    {
        return $this->dilihat === null
            ? null
            : $this->dasarKueri()->with([
                'depotAsal:id,nama', 'depotTujuan:id,nama', 'pengirim:id,name', 'penerima:id,name', 'pembatal:id,name',
                'items.produkAsal:id,nama,satuan', 'fotos.pengunggah:id,name',
            ])->find($this->dilihat);
    }

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterArah(): void
    {
        $this->resetPage();
    }

    public function bersihkanFilter(): void
    {
        $this->reset(['cari', 'filterStatus', 'filterArah']);
        $this->resetPage();
    }

    // ------------------------------------------------------------------
    // Kirim
    // ------------------------------------------------------------------

    /** Gudang ini sendiri harus gudang penyimpanan sebelum bisa mengirim. */
    #[Computed]
    public function depotSekarangGudangPenyimpanan(): bool
    {
        return (bool) DepotContext::currentOrFail()->gudang_penyimpanan;
    }

    /** @return Collection<int, Depot> */
    #[Computed]
    public function opsiGudangTujuan(): Collection
    {
        // gudang_penyimpanan ikut diambil walau kolomnya sudah pasti true
        // di sini (difilter lewat scope penyimpanan()) — TransferStokService::kirim()
        // membaca ulang atribut ini dari objek $tujuan yang sama, dan mode
        // atribut ketat Eloquent akan meledak kalau kolomnya tidak di-select.
        return Depot::query()->penyimpanan()->aktif()
            ->whereKeyNot(DepotContext::currentOrFail()->id)
            ->berurutan()
            ->get(['id', 'nama', 'gudang_penyimpanan']);
    }

    /** @return Collection<int, Produk> */
    #[Computed]
    public function produkTersedia(): Collection
    {
        return Produk::aktif()->orderBy('nama')->get();
    }

    public function buatBaruKirim(): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $this->reset(['depotTujuanId', 'catatanKirim']);
        $this->baris = [];
        $this->tambahBaris();
        $this->resetValidation();
        $this->formKirimTerbuka = true;
    }

    public function tutupFormKirim(): void
    {
        $this->formKirimTerbuka = false;
    }

    public function tambahBaris(): void
    {
        $this->baris[] = ['produk_id' => '', 'jumlah' => ''];
    }

    public function hapusBaris(int $indeks): void
    {
        unset($this->baris[$indeks]);
        $this->baris = array_values($this->baris);

        if ($this->baris === []) {
            $this->tambahBaris();
        }
    }

    public function kirim(TransferStokService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $data = $this->validate([
            'depotTujuanId' => ['required', 'integer'],
            'baris' => 'required|array|min:1',
            'baris.*.produk_id' => 'required|integer',
            'baris.*.jumlah' => 'required|integer|min:1',
            'catatanKirim' => 'nullable|string|max:1000',
        ], [], [
            'depotTujuanId' => __('transfer_stok.atr_gudang_tujuan'),
            'baris' => __('transfer_stok.atr_item'),
        ]);

        $tujuan = $this->opsiGudangTujuan->firstWhere('id', (int) $data['depotTujuanId']);

        if ($tujuan === null) {
            $this->addError('depotTujuanId', __('transfer_stok.galat_gudang_tujuan_tidak_valid'));

            return;
        }

        try {
            $transfer = $service->kirim(
                DepotContext::currentOrFail(),
                $tujuan,
                array_map(fn (array $b) => ['produk_id' => (int) $b['produk_id'], 'jumlah' => (int) $b['jumlah']], $data['baris']),
                $this->catatanKirim ?: null,
                auth()->user(),
            );
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $this->tutupFormKirim();
        unset($this->daftar, $this->ringkasan);

        $this->dispatch('notifikasi', pesan: __('transfer_stok.notif_dikirim', ['kode' => $transfer->kode, 'gudang' => $tujuan->nama]));
    }

    // ------------------------------------------------------------------
    // Terima
    // ------------------------------------------------------------------

    #[Computed]
    public function transferDiterima(): ?TransferStok
    {
        return $this->terimaId === null
            ? null
            : $this->dasarKueri()->dikirim()
                ->where('depot_tujuan_id', DepotContext::currentOrFail()->id)
                ->with('items.produkAsal:id,nama,satuan')
                ->find($this->terimaId);
    }

    public function bukaTerima(int $id): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $transfer = TransferStok::dikirim()->where('depot_tujuan_id', DepotContext::currentOrFail()->id)->with('items')->find($id);

        if ($transfer === null) {
            return;
        }

        $this->terimaId = $id;
        $this->jumlahTerima = $transfer->items->mapWithKeys(fn ($item) => [$item->id => $item->jumlah_kirim])->all();
        $this->dicekTerima = $transfer->items->mapWithKeys(fn ($item) => [$item->id => false])->all();
        $this->catatanTerima = '';
        $this->fotoTerima = [];
        $this->resetValidation();
    }

    public function tutupTerima(): void
    {
        $this->reset(['terimaId', 'jumlahTerima', 'dicekTerima', 'catatanTerima', 'fotoTerima']);
        $this->resetValidation();
    }

    public function hapusFotoTerima(int $indeks): void
    {
        unset($this->fotoTerima[$indeks]);
        $this->fotoTerima = array_values($this->fotoTerima);
    }

    /** Seluruh baris sudah dicentang — dipaksa, bukan sekadar warisan nilai bawaan. */
    #[Computed]
    public function semuaTercekTerima(): bool
    {
        return $this->dicekTerima !== [] && ! in_array(false, $this->dicekTerima, true);
    }

    public function terima(TransferStokService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        if (! $this->semuaTercekTerima) {
            $this->dispatch('notifikasi', pesan: __('transfer_stok.galat_belum_tercek'), jenis: 'error');

            return;
        }

        $this->validate([
            'catatanTerima' => 'nullable|string|max:1000',
            'fotoTerima.*' => 'nullable|image|max:5120',
        ]);

        $transfer = TransferStok::dikirim()->where('depot_tujuan_id', DepotContext::currentOrFail()->id)->find($this->terimaId);

        if ($transfer === null) {
            $this->tutupTerima();

            return;
        }

        $paths = array_map(
            fn ($foto) => $foto->store('transfer-stok/'.$transfer->id, 'public'),
            $this->fotoTerima,
        );

        try {
            $service->terima(
                $transfer,
                array_map('intval', $this->jumlahTerima),
                $this->catatanTerima ?: null,
                $paths,
                auth()->user(),
            );
        } catch (RuntimeException $e) {
            Storage::disk('public')->delete($paths);
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $kode = $transfer->kode;
        $this->tutupTerima();
        unset($this->daftar, $this->ringkasan);

        $this->dispatch('notifikasi', pesan: __('transfer_stok.notif_diterima', ['kode' => $kode]));
    }

    // ------------------------------------------------------------------
    // Batalkan
    // ------------------------------------------------------------------

    public function bukaBatalkan(int $id): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $transfer = TransferStok::dikirim()->where('depot_asal_id', DepotContext::currentOrFail()->id)->find($id);

        if ($transfer === null) {
            return;
        }

        $this->batalkanId = $id;
        $this->alasanBatal = '';
        $this->resetValidation();
    }

    public function tutupBatalkan(): void
    {
        $this->reset(['batalkanId', 'alasanBatal']);
        $this->resetValidation();
    }

    public function batalkan(TransferStokService $service): void
    {
        if ($this->tolakJikaTidakTerkunci()) {
            return;
        }

        $transfer = TransferStok::dikirim()->where('depot_asal_id', DepotContext::currentOrFail()->id)->find($this->batalkanId);

        if ($transfer === null) {
            $this->tutupBatalkan();

            return;
        }

        try {
            $service->batalkan($transfer, auth()->user(), $this->alasanBatal ?: null);
        } catch (RuntimeException $e) {
            $this->dispatch('notifikasi', pesan: $e->getMessage(), jenis: 'error');

            return;
        }

        $kode = $transfer->kode;
        $this->tutupBatalkan();
        unset($this->daftar, $this->ringkasan);

        $this->dispatch('notifikasi', pesan: __('transfer_stok.notif_dibatalkan', ['kode' => $kode]), jenis: 'info');
    }

    public function render()
    {
        return view('livewire.transfer-stok.daftar-transfer-stok')->title(__('transfer_stok.judul'));
    }
}
