<div>
    <x-judul-halaman :judul="__('tarik_freezer.judul')" :keterangan="__('tarik_freezer.ket')">
        <x-slot:aksi>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('tarik_freezer.baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    @if ($depotBelumDipilih)
        <x-butuh-depot-terkunci />
    @else
        {{-- Ringkasan status, sekaligus tombol saring cepat --}}
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($this->statusCases as $s)
                <button type="button"
                        wire:click="$set('filterStatus', '{{ $filterStatus === $s->value ? '' : $s->value }}')"
                        @class([
                            'rounded-xl border bg-white p-3 text-left transition hover:border-gray-300',
                            'border-blue-500 ring-1 ring-blue-500' => $filterStatus === $s->value,
                            'border-gray-200' => $filterStatus !== $s->value,
                        ])>
                    <x-badge-status :status="$s" />
                    <p class="mt-1.5 text-2xl font-semibold tabular-nums text-gray-900">{{ $this->ringkasan[$s->value] }}</p>
                </button>
            @endforeach
        </div>

        <x-kartu>
            <div class="flex flex-wrap items-end gap-3 border-b border-gray-100 p-4">
                <div class="min-w-56 flex-1">
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                    <input type="search" wire:model.live.debounce.400ms="cari" placeholder="{{ __('tarik_freezer.cari_placeholder') }}"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.wilayah') }}</label>
                    <select wire:model.live="filterWilayah"
                            class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ __('umum.semua_wilayah') }}</option>
                        @foreach ($this->wilayahs as $w)
                            <option value="{{ $w->id }}">{{ $w->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.tanggal') }}</label>
                    <input type="date" wire:model.live="filterTanggal"
                           class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('tarik_freezer.kolom_pengaju') }}</label>
                    <select wire:model.live="filterPengaju"
                            class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        <option value="">{{ __('tarik_freezer.semua_pengaju') }}</option>
                        @foreach ($this->pengajus as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" wire:click="bersihkanFilter"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.bersihkan') }}
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('tarik_freezer.kolom_kode') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('umum.toko') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('master.atr_wilayah') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('tarik_freezer.kolom_pengaju') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->daftar as $t)
                            <tr class="hover:bg-gray-50" wire:key="tarik-{{ $t->id }}">
                                <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">
                                    {{ $t->kode }}
                                    <span class="block text-xs font-normal text-gray-500">{{ $t->diajukan_at?->isoFormat('ll') }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="font-medium text-gray-900">{{ $t->toko->nama }}</span>
                                    <span class="block text-xs text-gray-500">{{ $t->toko->kode }}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $t->toko->wilayah?->nama }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $t->pengaju?->name }}</td>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span class="rounded px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $t->status->badge() }}">
                                        {{ $t->status->label() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <button type="button" wire:click="$set('dilihat', {{ $t->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.rincian') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-kosong ikon="arrow-uturn-left" :judul="__('tarik_freezer.kosong')" :keterangan="__('tarik_freezer.ket_kosong')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->daftar->hasPages())
                <div class="border-t border-gray-100 p-4">{{ $this->daftar->links() }}</div>
            @endif
        </x-kartu>
    @endif

    {{-- ============ Formulir pengajuan ============ --}}
    @if ($formTerbuka)
        <x-modal :judul="__('tarik_freezer.baru')" tutup="tutupForm">
            <div class="space-y-4 p-5">
                <p class="rounded-lg bg-rose-50 p-3 text-sm text-rose-900">{{ __('tarik_freezer.ket_form') }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('tarik_freezer.atr_toko') }}</label>
                    <x-pilih-cari :opsi="$this->opsiToko" :nilai="$tokoId" set="tokoId"
                                  placeholder="{{ __('tarik_freezer.pilih_toko_placeholder') }}"
                                  class="mt-1" />
                    @error('tokoId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @if ($this->opsiToko === [])
                        <p class="mt-1 text-xs text-amber-600">{{ __('tarik_freezer.tidak_ada_toko_bisa_diajukan') }}</p>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('tarik_freezer.atr_alasan') }}</label>
                    <textarea wire:model="alasan" rows="3" placeholder="{{ __('tarik_freezer.alasan_placeholder') }}"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                    @error('alasan') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.batal') }}
                </button>
                <button type="button" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="simpan">{{ __('tarik_freezer.tombol_ajukan') }}</span>
                    <span wire:loading wire:target="simpan">{{ __('umum.menyimpan') }}</span>
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- ============ Rincian ============ --}}
    @if ($this->detail)
        @php $d = $this->detail; @endphp
        <x-modal :judul="$d->kode" tutup="$set('dilihat', null)" lebar="max-w-xl">
            <div class="space-y-4 p-5">
                <div class="flex items-center gap-2">
                    <span class="rounded px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $d->status->badge() }}">
                        {{ $d->status->label() }}
                    </span>
                    <span class="text-xs text-gray-500">{{ $d->status->keterangan() }}</span>
                </div>

                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="font-semibold text-gray-900">{{ $d->toko->nama }} <span class="font-normal text-gray-500">({{ $d->toko->kode }})</span></p>
                    <p class="text-sm text-gray-600">{{ $d->toko->alamat }}</p>
                    <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                        <span>{{ __('master.atr_wilayah') }}: {{ $d->toko->wilayah?->nama }}</span>
                        @if ($d->toko->asset_id)
                            <span>{{ __('master.atr_asset_id') }}: <span class="font-mono">{{ $d->toko->asset_id }}</span></span>
                        @endif
                    </div>
                </div>

                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ __('tarik_freezer.atr_alasan') }}</p>
                    <p class="mt-1 whitespace-pre-line rounded-lg bg-gray-50 p-3 text-sm text-gray-700">{{ $d->alasan }}</p>
                </div>

                @if ($d->status === \App\Enums\StatusTarikFreezer::Ditolak)
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3">
                        <p class="text-sm font-semibold text-red-900">{{ __('tarik_freezer.alasan_tolak') }}</p>
                        <p class="mt-1 text-sm text-red-800">{{ $d->alasan_tolak }}</p>
                        <p class="mt-1 text-xs text-red-600">{{ $d->penolak?->name }} · {{ $d->ditolak_at?->isoFormat('lll') }}</p>
                    </div>
                @endif

                @if ($d->fotos->isNotEmpty())
                    <div>
                        <p class="mb-2 text-sm font-semibold text-gray-900">{{ __('tarik_freezer.judul_bukti_pengambilan') }}</p>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ($d->fotos as $foto)
                                <div>
                                    <a href="{{ route('tarik-freezer.foto', $foto) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('tarik-freezer.foto', $foto) }}" alt="{{ $foto->jenis->label() }}"
                                             class="h-32 w-full rounded-lg border border-gray-200 object-cover transition hover:opacity-90">
                                    </a>
                                    <p class="mt-1 text-xs font-medium text-gray-700">{{ $foto->jenis->label() }}</p>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            {{ __('tarik_freezer.selesai_oleh', ['nama' => $d->penyelesai?->name, 'waktu' => $d->selesai_at?->isoFormat('lll')]) }}
                        </p>
                    </div>
                @endif
            </div>
        </x-modal>
    @endif
</div>
