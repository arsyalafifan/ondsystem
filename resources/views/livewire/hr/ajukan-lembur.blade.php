@php
    $karyawan = $this->karyawan;
    $inputKelas = 'mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20';
    $fm = fn (?int $m) => \App\Models\PengajuanLembur::formatMenit($m);
@endphp

<div>
    <x-judul-halaman :judul="__('lembur.judul_ajukan')" :keterangan="__('lembur.ket_ajukan')" />

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
            {{-- ============ Form ============ --}}
            <div class="lg:col-span-2">
                <x-kartu>
                    <form wire:submit="ajukan" class="space-y-4 p-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('lembur.atr_tanggal') }}</label>
                            <input type="date" wire:model.live="tanggal" min="{{ today()->toDateString() }}" class="{{ $inputKelas }}">
                            @error('tanggal') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('lembur.atr_jam_mulai') }}</label>
                                <input type="time" wire:model.live="jamMulai" class="{{ $inputKelas }}">
                                @error('jamMulai') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('lembur.atr_jam_selesai') }}</label>
                                <input type="time" wire:model.live="jamSelesai" class="{{ $inputKelas }}">
                                @error('jamSelesai') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <p class="text-xs text-gray-500">
                            {{ __('lembur.ket_jam_kerja', [
                                'masuk' => substr($karyawan->shift?->jam_masuk ?? $karyawan->posisi->jam_masuk, 0, 5),
                                'pulang' => substr($karyawan->shift?->jam_pulang ?? $karyawan->posisi->jam_pulang, 0, 5),
                            ]) }}
                        </p>

                        @if ($p = $this->pratinjau)
                            <div @class([
                                'rounded-lg border p-3 text-sm',
                                'border-amber-200 bg-amber-50 text-amber-900' => $p['menit'] > $p['diakui'],
                                'border-blue-200 bg-blue-50 text-blue-900' => $p['menit'] <= $p['diakui'],
                            ])>
                                <p class="font-medium">{{ __('lembur.pratinjau_durasi', ['durasi' => $fm($p['menit'])]) }}
                                    @if ($p['lintas']) <span class="font-normal">· {{ __('lembur.lewat_tengah_malam') }}</span> @endif
                                </p>
                                @if ($p['menit'] > $p['diakui'])
                                    <p class="mt-0.5">{{ __('lembur.pratinjau_dibatasi', ['batas' => $fm($this->batasMenit)]) }}</p>
                                @else
                                    <p class="mt-0.5">{{ __('lembur.pratinjau_diakui', ['durasi' => $fm($p['diakui'])]) }}</p>
                                @endif
                                <p class="mt-1 text-xs opacity-80">{{ __('lembur.ket_realisasi') }}</p>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('lembur.atr_tugas') }}</label>
                            <textarea wire:model="tugas" rows="3" placeholder="{{ __('lembur.tugas_contoh') }}" class="{{ $inputKelas }}"></textarea>
                            @error('tugas') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit" wire:loading.attr="disabled" wire:target="ajukan"
                                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="ajukan">{{ __('lembur.tombol_ajukan') }}</span>
                            <span wire:loading wire:target="ajukan">{{ __('umum.menyimpan') }}</span>
                        </button>
                    </form>
                </x-kartu>
            </div>

            {{-- ============ Riwayat ============ --}}
            <div class="lg:col-span-3">
                <x-kartu>
                    <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900">{{ __('lembur.riwayat') }}</div>
                    @if ($this->riwayat->isEmpty())
                        <x-kosong ikon="clock" :judul="__('lembur.riwayat_kosong')" />
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($this->riwayat as $r)
                                @php $h = $this->hitungan[$r->id]; @endphp
                                <li class="p-4" wire:key="lembur-{{ $r->id }}">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $r->tanggal->isoFormat('dddd, D MMM Y') }}</p>
                                            <p class="text-sm tabular-nums text-gray-600">{{ $r->jamTeks() }} · {{ $fm($r->menit_diajukan) }}</p>
                                        </div>
                                        <span class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium {{ $r->status->badge() }}">{{ $r->status->label() }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-700">{{ $r->tugas }}</p>

                                    @if ($r->status === \App\Enums\StatusPengajuan::Disetujui)
                                        <p class="mt-1 text-xs">
                                            @if ($h['diakui'] === null)
                                                <span class="text-gray-500">{{ __('lembur.belum_terealisasi', ['rencana' => $fm($h['rencana'])]) }}</span>
                                            @else
                                                <span class="font-medium text-emerald-700">{{ __('lembur.diakui', ['durasi' => $fm($h['diakui'])]) }}</span>
                                                <span class="text-gray-500">· {{ __('lembur.realisasi', ['durasi' => $fm($h['realisasi'])]) }}</span>
                                            @endif
                                        </p>
                                    @endif

                                    @if (($this->penyetuju[$r->id] ?? '') !== '')
                                        <p class="mt-1 text-xs text-amber-700">{{ __('izin.menunggu_persetujuan', ['nama' => $this->penyetuju[$r->id]]) }}</p>
                                    @endif
                                    @if ($r->catatan_keputusan)
                                        <p class="mt-2 rounded-lg bg-gray-50 p-2 text-xs text-gray-600">
                                            <span class="font-medium">{{ $r->diputuskanOleh?->name ?? 'HR' }}:</span> {{ $r->catatan_keputusan }}
                                        </p>
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
