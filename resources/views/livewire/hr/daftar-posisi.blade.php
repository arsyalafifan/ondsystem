<div>
    <x-judul-halaman :judul="__('hr.judul_posisi')" :keterangan="__('hr.ket_posisi')">
        <x-slot:aksi>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="unduhExcel"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ __('hr.ekspor_excel') }}
                </button>
                <button type="button" wire:click="$set('imporTerbuka', true)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ __('hr.impor_excel') }}
                </button>
                <button type="button" wire:click="buatBaru"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ __('hr.posisi_baru') }}
                </button>
            </div>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('umum.kode') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.nama') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_kondisi_absen') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('hr.jumlah_karyawan') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->posisis as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $p->kode }}</td>
                            <td class="px-4 py-2">{{ $p->nama }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $p->lokasi_jenis->label() }}</td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-100 text-emerald-800' => $p->aktif,
                                    'bg-gray-100 text-gray-600' => ! $p->aktif,
                                ])>{{ $p->aktif ? __('umum.aktif') : __('umum.nonaktif') }}</span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $p->karyawans_count }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="sunting({{ $p->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.sunting') }}
                                    </button>
                                    <button type="button" wire:click="$set('konfirmasiHapus', {{ $p->id }})"
                                            @disabled($p->karyawans_count > 0)
                                            class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400">
                                        {{ __('umum.hapus') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-kosong ikon="user-group" :judul="__('hr.posisi_kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-kartu>

    @if ($formTerbuka)
        <x-modal :judul="$posisiId ? __('hr.judul_posisi_sunting') : __('hr.judul_posisi_baru')" tutup="tutupForm">
            <div class="space-y-3 p-5">
                <p class="rounded-lg bg-blue-50 p-3 text-xs text-blue-800">{{ __('hr.ket_posisi_form') }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('umum.kode') }}</label>
                    <input type="text" wire:model="kode"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('kode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('umum.nama') }}</label>
                    <input type="text" wire:model="nama"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('nama') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="aktif" class="size-4 rounded border-gray-400 text-blue-600 focus:ring-blue-500">
                    {{ __('umum.aktif') }}
                </label>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.batal') }}
                </button>
                <button type="button" wire:click="simpan"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ __('umum.simpan') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @if ($konfirmasiHapus)
        <x-modal :judul="__('hr.judul_hapus_posisi')" tutup="$set('konfirmasiHapus', null)">
            <div class="p-5 text-sm text-gray-600">{{ __('hr.ket_hapus_posisi') }}</div>

            <x-slot:aksi>
                <button type="button" wire:click="$set('konfirmasiHapus', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    {{ __('umum.batal') }}
                </button>
                <button type="button" wire:click="hapus({{ $konfirmasiHapus }})"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    {{ __('umum.hapus') }}
                </button>
            </x-slot:aksi>
        </x-modal>
    @endif

    {{-- Modal Impor CSV / Excel --}}
    @if ($imporTerbuka)
        <x-modal :judul="__('hr.impor_posisi_judul')" tutup="$set('imporTerbuka', false)">
            <div class="space-y-4 p-5">
                @if (! $imporBerjalan)
                    <div class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
                        <p class="font-medium">{{ __('hr.petunjuk_impor_posisi') }}</p>
                        <ul class="mt-1 list-disc pl-5 text-xs text-blue-800 space-y-1">
                            <li>{{ __('hr.ket_impor_posisi_update') }}</li>
                            <li>{{ __('hr.ket_impor_posisi_kolom') }}</li>
                        </ul>
                        <div class="mt-3 flex flex-wrap items-center gap-4 text-xs">
                            <button type="button" wire:click="unduhContohExcel"
                                    class="font-semibold text-blue-700 underline hover:text-blue-900">
                                ⬇ {{ __('hr.contoh_format_csv') }}
                            </button>
                            <button type="button" wire:click="unduhExcel"
                                    class="font-semibold text-blue-700 underline hover:text-blue-900">
                                ⬇ {{ __('hr.ekspor_excel') }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('hr.unggah_berkas_impor') }}</label>
                        <input type="file" wire:model="berkasCsv" accept=".csv,.xlsx,.xls,text/csv"
                               class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700">
                        @error('berkasCsv') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="berkasCsv" class="mt-1 text-sm text-gray-500">{{ __('umum.mengunggah') }}</div>
                    </div>
                @else
                    <div wire:poll.300ms="lanjutkanImporCsv">
                        <div class="flex items-center justify-between text-sm text-gray-700">
                            <span>{{ __('hr.proses_impor') }}</span>
                            <span class="tabular-nums">{{ $imporOffset }} / {{ $imporTotal }}</span>
                        </div>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                            <div class="h-full rounded-full bg-blue-600 transition-all"
                                 style="width: {{ $imporTotal > 0 ? min(100, round($imporOffset / $imporTotal * 100)) : 0 }}%"></div>
                        </div>
                    </div>
                @endif

                @if ($hasilImpor)
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm">
                        <p class="font-medium text-emerald-900">
                            {{ __('hr.hasil_impor_posisi', ['baru' => $hasilImpor['baru'], 'diperbarui' => $hasilImpor['diperbarui']]) }}
                        </p>
                        @if ($hasilImpor['dilewati'])
                            <p class="mt-2 font-medium text-amber-900">
                                {{ __('hr.baris_dilewati', ['jumlah' => count($hasilImpor['dilewati'])]) }}
                            </p>
                            <ul class="mt-1 max-h-32 space-y-0.5 overflow-y-auto text-xs text-amber-800">
                                @foreach ($hasilImpor['dilewati'] as $baris)
                                    <li>• {{ $baris }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>

            <x-slot:aksi>
                @if ($imporBerjalan)
                    <button type="button" wire:click="batalkanImporCsv"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                        {{ __('hr.batalkan') }}
                    </button>
                @else
                    <button type="button" wire:click="$set('imporTerbuka', false)"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                        {{ __('hr.tutup') }}
                    </button>
                    <button type="button" wire:click="mulaiImporCsv" wire:loading.attr="disabled" wire:target="mulaiImporCsv,berkasCsv"
                            class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="mulaiImporCsv">{{ __('hr.mulai_impor') }}</span>
                        <span wire:loading wire:target="mulaiImporCsv">{{ __('umum.memproses') }}</span>
                    </button>
                @endif
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
