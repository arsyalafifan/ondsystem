@php
    $karyawan = $this->karyawan;
    $inputKelas = 'mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20';
    $jenisDipilih = \App\Enums\JenisIzin::tryFrom($jenis);
    $setengah = \App\Enums\PorsiIzin::tryFrom($porsi)?->setengahHari() ?? false;
@endphp

<div>
    <x-judul-halaman :judul="__('izin.judul_ajukan')" :keterangan="__('izin.ket_ajukan')" />

    @if ($karyawan === null)
        <x-kartu>
            <x-kosong ikon="identification" :judul="__('hr.galat_tanpa_karyawan')" :keterangan="__('hr.ket_tanpa_karyawan')" />
        </x-kartu>
    @elseif ($karyawan->posisi === null)
        <x-kartu>
            <x-kosong ikon="user-group" :judul="__('hr.galat_posisi_kosong')" :keterangan="__('hr.ket_posisi_kosong')" />
        </x-kartu>
    @else
        <div class="grid gap-4 lg:grid-cols-5">
            {{-- ============ Form pengajuan ============ --}}
            <div class="lg:col-span-2">
                <x-kartu>
                    <form wire:submit="ajukan" class="space-y-4 p-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('izin.atr_jenis') }}</label>
                            <div class="mt-1 grid grid-cols-2 gap-2">
                                @foreach (\App\Enums\JenisIzin::cases() as $j)
                                    <label @class([
                                        'flex cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition',
                                        'border-blue-600 bg-blue-50 text-blue-700' => $jenis === $j->value,
                                        'border-gray-300 text-gray-700 hover:bg-gray-50' => $jenis !== $j->value,
                                    ])>
                                        <input type="radio" wire:model.live="jenis" value="{{ $j->value }}" class="sr-only">
                                        {{ $j->label() }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        @if ($jenisDipilih?->bolehSetengahHari())
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('izin.atr_porsi') }}</label>
                                <div class="mt-1 grid grid-cols-3 gap-2">
                                    @foreach (\App\Enums\PorsiIzin::cases() as $p)
                                        <label @class([
                                            'flex cursor-pointer items-center justify-center rounded-lg border px-2 py-2.5 text-center text-xs font-medium transition sm:text-sm',
                                            'border-blue-600 bg-blue-50 text-blue-700' => $porsi === $p->value,
                                            'border-gray-300 text-gray-700 hover:bg-gray-50' => $porsi !== $p->value,
                                        ])>
                                            <input type="radio" wire:model.live="porsi" value="{{ $p->value }}" class="sr-only">
                                            {{ $p->label() }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div @class(['grid gap-3', 'grid-cols-2' => ! $setengah])>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ $setengah ? __('umum.tanggal') : __('izin.atr_tanggal_mulai') }}</label>
                                <input type="date" wire:model.live="tanggalMulai" class="{{ $inputKelas }}">
                                @error('tanggalMulai') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            @unless ($setengah)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('izin.atr_tanggal_selesai') }}</label>
                                    <input type="date" wire:model.live="tanggalSelesai" min="{{ $tanggalMulai }}" class="{{ $inputKelas }}">
                                    @error('tanggalSelesai') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endunless
                        </div>

                        @if ($jenisDipilih)
                            <p class="text-xs text-gray-500">
                                {{ $jenisDipilih->batasMundurHari() === 0
                                    ? __('izin.ket_batas_izin')
                                    : __('izin.ket_batas_sakit', ['hari' => $jenisDipilih->batasMundurHari()]) }}
                            </p>
                        @endif

                        @if ($pratinjau = $this->pratinjau)
                            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                                <p class="font-medium">{{ __('izin.pratinjau_hari', ['hari' => \App\Support\Bahasa::angka($pratinjau['hari'], $setengah ? 1 : 0)]) }}</p>
                                @if ($pratinjau['jam'])
                                    <p class="mt-0.5">{{ $pratinjau['jam'] }}</p>
                                @endif
                                @if ($jenisDipilih && ! $jenisDipilih->dibayar())
                                    <p class="mt-0.5 text-xs text-blue-800/80">{{ __('izin.ket_tidak_dibayar') }}</p>
                                @endif
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('izin.atr_alasan') }}</label>
                            <textarea wire:model="alasan" rows="3" placeholder="{{ __('izin.alasan_contoh') }}" class="{{ $inputKelas }}"></textarea>
                            @error('alasan') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                {{ __('izin.atr_lampiran') }} <span class="font-normal text-gray-400">({{ __('umum.opsional') }})</span>
                            </label>
                            <input type="file" wire:model="lampiran" accept="image/*,application/pdf"
                                   class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700">
                            <p class="mt-1 text-xs text-gray-500">{{ __('izin.ket_lampiran') }}</p>
                            <div wire:loading wire:target="lampiran" class="mt-1 text-sm text-gray-500">{{ __('umum.mengunggah') }}</div>
                            @error('lampiran') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit" wire:loading.attr="disabled" wire:target="ajukan,lampiran"
                                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="ajukan">{{ __('izin.tombol_ajukan') }}</span>
                            <span wire:loading wire:target="ajukan">{{ __('umum.menyimpan') }}</span>
                        </button>
                    </form>
                </x-kartu>
            </div>

            {{-- ============ Riwayat pengajuan sendiri ============ --}}
            <div class="lg:col-span-3">
                <x-kartu>
                    <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900">{{ __('izin.riwayat') }}</div>
                    @if ($this->riwayat->isEmpty())
                        <x-kosong ikon="document-text" :judul="__('izin.riwayat_kosong')" />
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($this->riwayat as $r)
                                <li class="p-4" wire:key="izin-{{ $r->id }}">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-900">
                                                {{ $r->jenis->label() }}
                                                @if ($r->porsi->setengahHari()) · {{ $r->porsi->label() }} @endif
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                {{ $r->tanggalTeks() }} · {{ __('izin.jumlah_hari', ['hari' => \App\Support\Bahasa::angka($r->jumlah_hari, fmod($r->jumlah_hari, 1) > 0 ? 1 : 0)]) }}
                                            </p>
                                        </div>
                                        <span class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium {{ $r->status->badge() }}">{{ $r->status->label() }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-700">{{ $r->alasan }}</p>
                                    @if ($r->lampiran)
                                        <a href="{{ route('hr.izin.lampiran', $r) }}" target="_blank" rel="noopener"
                                           class="mt-1 inline-flex items-center gap-1 text-xs text-blue-700 underline">
                                            <x-heroicon-o-paper-clip class="size-3.5" /> {{ $r->lampiran_nama ?? __('izin.atr_lampiran') }}
                                        </a>
                                    @endif
                                    @if ($r->catatan_keputusan)
                                        <p class="mt-2 rounded-lg bg-gray-50 p-2 text-xs text-gray-600">
                                            <span class="font-medium">{{ $r->diputuskanOleh?->name ?? 'HR' }}:</span> {{ $r->catatan_keputusan }}
                                        </p>
                                    @endif
                                    @if (($this->penyetuju[$r->id] ?? '') !== '')
                                        <p class="mt-1 text-xs text-amber-700">{{ __('izin.menunggu_persetujuan', ['nama' => $this->penyetuju[$r->id]]) }}</p>
                                    @endif
                                    @if ($r->status === \App\Enums\StatusPengajuan::Menunggu)
                                        <div class="mt-2">
                                            @if ($konfirmasiBatal === $r->id)
                                                <span class="text-xs text-gray-600">{{ __('izin.konfirmasi_batal') }}</span>
                                                <button type="button" wire:click="batalkan({{ $r->id }})"
                                                        class="ml-2 rounded-md bg-red-600 px-2 py-1 text-xs font-semibold text-white hover:bg-red-700">{{ __('izin.ya_tarik') }}</button>
                                                <button type="button" wire:click="$set('konfirmasiBatal', null)"
                                                        class="ml-1 rounded-md border border-gray-300 px-2 py-1 text-xs font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                                            @else
                                                <button type="button" wire:click="$set('konfirmasiBatal', {{ $r->id }})"
                                                        class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">{{ __('izin.tombol_tarik') }}</button>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-kartu>
            </div>
        </div>
    @endif
</div>
