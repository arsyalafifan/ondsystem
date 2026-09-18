@php
    $inputKelas = 'mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20';
    $warnaJenis = [
        'berangkat' => 'bg-blue-100 text-blue-800',
        'kembali' => 'bg-emerald-100 text-emerald-800',
        'pengisian' => 'bg-amber-100 text-amber-800',
    ];
@endphp

<div>
    <x-judul-halaman :judul="__('kendaraan.judul_monitoring')" :keterangan="__('kendaraan.ket_monitoring')" />

    <x-kartu>
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.tanggal') }}</label>
                <input type="date" wire:model.live="tanggal" class="{{ $inputKelas }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('kendaraan.kolom_driver') }}</label>
                <select wire:model.live="filterDriver" class="{{ $inputKelas }}">
                    <option value="">{{ __('kendaraan.semua_driver') }}</option>
                    @foreach ($this->drivers as $driver)
                        <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('kendaraan.kolom_jenis') }}</label>
                <select wire:model.live="filterJenis" class="{{ $inputKelas }}">
                    <option value="">{{ __('kendaraan.semua_jenis') }}</option>
                    @foreach (\App\Enums\JenisCatatanBbm::cases() as $j)
                        <option value="{{ $j->value }}">{{ $j->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-5">
            @php $r = $this->ringkasan; @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">{{ __('kendaraan.jenis_berangkat') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($r['berangkat'])</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">{{ __('kendaraan.jenis_kembali') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($r['kembali'])</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">{{ __('kendaraan.ringkasan_total_pengisian') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($r['pengisian'])</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">{{ __('kendaraan.ringkasan_total_liter') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka($r['liter'], 2)</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-3">
                <p class="text-xs text-gray-500">{{ __('kendaraan.ringkasan_total_biaya') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">Rp @angka($r['biaya'])</p>
            </div>
        </div>

        <div class="overflow-x-auto border-t border-gray-200">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('kendaraan.kolom_waktu') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('kendaraan.kolom_kendaraan') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('kendaraan.kolom_driver') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('kendaraan.kolom_jenis') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('kendaraan.kolom_km') }} / {{ __('kendaraan.kolom_bbm') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('kendaraan.kolom_liter') }} / {{ __('kendaraan.kolom_biaya') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('kendaraan.kolom_foto') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->catatans as $c)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 tabular-nums text-gray-600">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $c->kendaraan->nama }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $c->kendaraan->driver?->name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium {{ $warnaJenis[$c->jenis->value] }}">
                                    {{ $c->jenis->label() }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 tabular-nums text-gray-600">
                                @if ($c->km !== null)
                                    @angka($c->km) · {{ $c->level_bbm->label() }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 tabular-nums text-gray-600">
                                @if ($c->liter !== null || $c->biaya !== null)
                                    @if ($c->liter !== null) @angka($c->liter, 2) L @endif
                                    @if ($c->biaya !== null) · Rp @angka($c->biaya) @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <button type="button" wire:click="$set('fotoDilihat', {{ $c->id }})">
                                    <img src="{{ $c->url_foto }}" alt="" class="size-10 rounded object-cover ring-1 ring-gray-200 hover:ring-blue-400">
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-kosong ikon="fire" :judul="__('kendaraan.monitoring_kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @if ($this->foto !== null)
        @php $f = $this->foto; @endphp
        <x-modal :judul="$f->kendaraan->nama.' · '.$f->jenis->label()" tutup="$set('fotoDilihat', null)" lebar="max-w-xl">
            <div class="space-y-4 p-5">
                <div>
                    <img src="{{ $f->url_foto }}" alt="" class="w-full rounded-lg border border-gray-200">
                </div>

                @if ($f->foto_sebelum || $f->foto_sesudah)
                    <div class="grid grid-cols-2 gap-3">
                        @if ($f->foto_sebelum)
                            <div>
                                <p class="mb-1 text-xs font-medium text-gray-500">{{ __('kendaraan.label_sebelum_isi') }}</p>
                                <img src="{{ $f->url_foto_sebelum }}" alt="" class="w-full rounded-lg border border-gray-200">
                            </div>
                        @endif
                        @if ($f->foto_sesudah)
                            <div>
                                <p class="mb-1 text-xs font-medium text-gray-500">{{ __('kendaraan.label_sesudah_isi') }}</p>
                                <img src="{{ $f->url_foto_sesudah }}" alt="" class="w-full rounded-lg border border-gray-200">
                            </div>
                        @endif
                    </div>
                @endif

                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('kendaraan.kolom_waktu') }}</dt>
                        <dd class="tabular-nums text-gray-900">{{ $f->created_at->format('d/m/Y H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('kendaraan.kolom_driver') }}</dt>
                        <dd class="text-gray-900">{{ $f->dicatatOleh?->name ?? '—' }}</dd>
                    </div>
                    @if ($f->km !== null)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('kendaraan.kolom_km') }}</dt>
                            <dd class="tabular-nums text-gray-900">@angka($f->km)</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('kendaraan.kolom_bbm') }}</dt>
                            <dd class="text-gray-900">{{ $f->level_bbm->label() }}</dd>
                        </div>
                    @endif
                    @if ($f->liter !== null)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('kendaraan.kolom_liter') }}</dt>
                            <dd class="tabular-nums text-gray-900">@angka($f->liter, 2) L</dd>
                        </div>
                    @endif
                    @if ($f->biaya !== null)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('kendaraan.kolom_biaya') }}</dt>
                            <dd class="tabular-nums text-gray-900">Rp @angka($f->biaya)</dd>
                        </div>
                    @endif
                    @if ($f->catatan)
                        <div class="col-span-2">
                            <dt class="text-xs text-gray-500">{{ __('umum.catatan_opsional') }}</dt>
                            <dd class="text-gray-900">{{ $f->catatan }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </x-modal>
    @endif
</div>
