<div>
    <x-judul-halaman :judul="__('master.judul_freezer')" :keterangan="__('master.ket_freezer')">
        <x-slot:aksi>
            <button type="button" wire:click="unduhExcel"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('master.ekspor_excel') }}
            </button>
            <button type="button" wire:click="$set('imporTerbuka', true)"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                {{ __('master.impor_csv') }}
            </button>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('master.freezer_baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="min-w-56 flex-1">
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                <input type="search" wire:model.live.debounce.300ms="cari" placeholder="{{ __('master.cari_freezer') }}"
                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.status') }}</label>
                <select wire:model.live="filterStatus"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    <option value="">{{ __('umum.semua_status') }}</option>
                    <option value="1">{{ __('umum.aktif') }}</option>
                    <option value="0">{{ __('umum.nonaktif') }}</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('master.idn_freezer') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('master.tipe_freezer') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.keterangan') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('master.kolom_nama_toko') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('master.kolom_gudang') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->freezers as $f)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-2 font-mono font-medium text-gray-900">{{ $f->idn }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-800">{{ $f->tipe }}</td>
                            <td class="max-w-md truncate px-4 py-2 text-gray-600">{{ $f->keterangan ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-800">
                                @if ($f->toko)
                                    {{ $f->toko->nama }}
                                    <span class="ml-1 font-mono text-xs text-gray-500">{{ $f->toko->kode }}</span>
                                @else
                                    <span class="rounded bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">{{ __('master.freezer_belum_terpasang') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $f->toko?->depot?->nama ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @if ($f->aktif)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('umum.aktif') }}</span>
                                @else
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('umum.nonaktif') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="sunting({{ $f->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.sunting') }}
                                    </button>
                                    <button type="button" wire:click="$set('konfirmasiHapus', {{ $f->id }})"
                                            @disabled($f->toko !== null)
                                            title="{{ $f->toko !== null ? __('master.freezer_dipakai_toko') : '' }}"
                                            class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400">
                                        {{ __('umum.hapus') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-kosong ikon="cube" :judul="__('master.freezer_kosong')" :keterangan="__('master.freezer_kosong_ket')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->freezers->hasPages())
            <div class="border-t border-gray-200 p-4">
                {{ $this->freezers->links() }}
            </div>
        @endif
    </x-kartu>

    {{-- Modal Tambah / Sunting --}}
    @if ($formTerbuka)
        <x-modal :judul="$freezerId ? __('master.judul_freezer_sunting') : __('master.judul_freezer_baru')" tutup="tutupForm">
            <div class="space-y-3 p-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('master.idn_freezer') }}</label>
                    <input type="text" wire:model="idn" placeholder="misal: IDNAH202528004381"
                           class="mt-1 block w-full font-mono uppercase rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('idn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-500">{{ __('master.ket_idn_unik') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('master.tipe_freezer') }}</label>
                    <input type="text" wire:model="tipe" placeholder="misal: SD-200 / Chest Freezer 300L"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('tipe') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('umum.keterangan') }}</label>
                    <textarea wire:model="keterangan" rows="2" placeholder="{{ __('umum.catatan_opsional') }}"
                              class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                    @error('keterangan') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="aktif"
                           class="rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    {{ __('master.freezer_aktif') }}
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

    {{-- Modal Konfirmasi Hapus --}}
    @if ($konfirmasiHapus)
        <x-modal :judul="__('master.judul_hapus_freezer')" tutup="$set('konfirmasiHapus', null)">
            <p class="p-5 text-sm text-gray-600">{{ __('master.ket_hapus_freezer') }}</p>
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
        <x-modal :judul="__('master.impor_freezer_judul')" tutup="$set('imporTerbuka', false)">
            <div class="space-y-4 p-5">
                @if (! $imporBerjalan)
                    <div class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
                        <p class="font-medium">{{ __('master.petunjuk_impor') }}</p>
                        <ul class="mt-1 list-disc pl-5 text-xs text-blue-800 space-y-1">
                            <li>{{ __('master.ket_impor_freezer_format') }}</li>
                            <li>{{ __('master.ket_impor_freezer_update') }}</li>
                            <li>{{ __('master.ket_impor_freezer_kolom') }}</li>
                        </ul>
                        <div class="mt-3 flex flex-wrap items-center gap-4 text-xs">
                            <button type="button" wire:click="unduhContohExcel"
                                    class="font-semibold text-blue-700 underline hover:text-blue-900">
                                ⬇ {{ __('master.unduh_contoh_template') }}
                            </button>
                            <button type="button" wire:click="unduhExcel"
                                    class="font-semibold text-blue-700 underline hover:text-blue-900">
                                ⬇ {{ __('master.ekspor_excel') }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('master.berkas_csv_excel') }}</label>
                        <input type="file" wire:model="berkasCsv" accept=".csv,.xlsx,.xls,text/csv"
                               class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700">
                        @error('berkasCsv') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="berkasCsv" class="mt-1 text-sm text-gray-500">{{ __('umum.mengunggah') }}</div>
                    </div>
                @else
                    <div wire:poll.300ms="lanjutkanImporCsv">
                        <div class="flex items-center justify-between text-sm text-gray-700">
                            <span>{{ __('master.impor_berjalan') }}</span>
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
                            {{ __('master.hasil_impor_freezer', ['baru' => $hasilImpor['baru'], 'diperbarui' => $hasilImpor['diperbarui']]) }}
                        </p>
                        @if ($hasilImpor['dilewati'])
                            <p class="mt-2 font-medium text-amber-900">
                                {{ __('master.baris_dilewati', ['jumlah' => count($hasilImpor['dilewati'])]) }}
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
                        {{ __('umum.batal') }}
                    </button>
                @else
                    <button type="button" wire:click="$set('imporTerbuka', false)"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                        {{ __('umum.tutup') }}
                    </button>
                    <button type="button" wire:click="mulaiImporCsv" wire:loading.attr="disabled" wire:target="mulaiImporCsv,berkasCsv"
                            class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="mulaiImporCsv">{{ __('master.proses_impor') }}</span>
                        <span wire:loading wire:target="mulaiImporCsv">{{ __('umum.memproses') }}</span>
                    </button>
                @endif
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
