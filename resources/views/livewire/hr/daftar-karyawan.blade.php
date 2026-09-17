<div>
    <x-judul-halaman :judul="__('hr.judul_karyawan')" :keterangan="__('hr.ket_karyawan')">
        <x-slot:aksi>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('hr.karyawan_baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="border-b border-gray-200 p-4">
            <input type="search" wire:model.live.debounce.300ms="cari" placeholder="{{ __('hr.cari_karyawan') }}"
                   class="block w-full max-w-sm rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium"></th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_kode_karyawan') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_nama_lengkap') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_department') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_jabatan') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_penempatan') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('hr.atr_status_karyawan') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->karyawans as $k)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">
                                @if ($k->foto_karyawan)
                                    <img src="{{ $k->url_foto_karyawan }}" alt="{{ $k->nama_lengkap }}"
                                         class="size-9 rounded-full object-cover">
                                @else
                                    <div class="grid size-9 place-items-center rounded-full bg-blue-100 text-sm font-bold text-blue-600">
                                        {{ substr($k->nama_lengkap, 0, 1) }}
                                    </div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $k->kode_karyawan }}</td>
                            <td class="px-4 py-2">
                                {{ $k->nama_lengkap }}
                                <span class="block text-xs text-gray-500">{{ $k->nik }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ $k->department->nama }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $k->jabatan->nama }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $k->depot->nama }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ $k->status_karyawan->label() }}</span>
                            </td>
                            <td class="px-4 py-2">
                                @if ($k->aktif)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('umum.aktif') }}</span>
                                @else
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('umum.nonaktif') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="sunting({{ $k->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.sunting') }}
                                    </button>
                                    <button type="button" wire:click="$set('konfirmasiHapus', {{ $k->id }})"
                                            class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                        {{ __('umum.hapus') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-kosong ikon="identification" :judul="__('hr.karyawan_kosong')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->karyawans->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">{{ $this->karyawans->links() }}</div>
        @endif
    </x-kartu>

    @if ($formTerbuka)
        <x-modal :judul="$karyawanId ? __('hr.judul_karyawan_sunting') : __('hr.judul_karyawan_baru')" lebar="max-w-4xl" tutup="tutupForm">
            <div class="max-h-[75vh] space-y-5 overflow-y-auto p-5">
                {{-- Identitas --}}
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('hr.bagian_identitas') }}</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_kode_karyawan') }}</label>
                            <input type="text" wire:model="kodeKaryawan"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('kodeKaryawan') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_nik') }}</label>
                            <input type="text" wire:model="nik" maxlength="16" inputmode="numeric"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('nik') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_nama_lengkap') }}</label>
                            <input type="text" wire:model="namaLengkap"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('namaLengkap') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_jenis_kelamin') }}</label>
                            <select wire:model="jenisKelamin"
                                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                @foreach ($this->jenisKelaminCases as $jk)
                                    <option value="{{ $jk->value }}">{{ $jk->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_tanggal_lahir') }}</label>
                            <input type="date" wire:model="tanggalLahir"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('tanggalLahir') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_no_hp') }}</label>
                            <input type="text" wire:model="noHp"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('noHp') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_alamat_domisili') }}</label>
                            <textarea wire:model="alamatDomisili" rows="2"
                                      class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                            @error('alamatDomisili') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Kepegawaian --}}
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('hr.bagian_kepegawaian') }}</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_department') }}</label>
                            <select wire:model="departmentId"
                                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                <option value="">{{ __('hr.pilih_department') }}</option>
                                @foreach ($this->departments as $d)
                                    <option value="{{ $d->id }}">{{ $d->nama }}</option>
                                @endforeach
                            </select>
                            @error('departmentId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_jabatan') }}</label>
                            <select wire:model="jabatanId"
                                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                <option value="">{{ __('hr.pilih_jabatan') }}</option>
                                @foreach ($this->jabatans as $j)
                                    <option value="{{ $j->id }}">{{ $j->nama }}</option>
                                @endforeach
                            </select>
                            @error('jabatanId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_posisi') }}</label>
                            <select wire:model="posisiId"
                                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                <option value="">{{ __('hr.pilih_posisi') }}</option>
                                @foreach ($this->posisis as $p)
                                    <option value="{{ $p->id }}">{{ $p->nama }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">{{ __('hr.ket_posisi_karyawan') }}</p>
                            @error('posisiId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_shift') }}</label>
                            <select wire:model="shiftId"
                                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                <option value="">{{ __('hr.shift_normal') }}</option>
                                @foreach ($this->shifts as $s)
                                    <option value="{{ $s->id }}">{{ $s->nama }} ({{ substr($s->jam_masuk, 0, 5) }}–{{ substr($s->jam_pulang, 0, 5) }})</option>
                                @endforeach
                            </select>
                            @error('shiftId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_penempatan') }}</label>
                            <select wire:model="depotId"
                                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                <option value="">{{ __('auth.pilih_depot') }}</option>
                                @foreach ($this->depots as $dp)
                                    <option value="{{ $dp->id }}">{{ $dp->nama }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">{{ __('hr.ket_penempatan') }}</p>
                            @error('depotId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_tanggal_masuk') }}</label>
                            <input type="date" wire:model="tanggalMasuk"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('tanggalMasuk') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_status_karyawan') }}</label>
                            <select wire:model.live="statusKaryawan"
                                    class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                @foreach ($this->statusKaryawanCases as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($statusKaryawan === \App\Enums\StatusKaryawan::Kontrak->value)
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_tanggal_berakhir_kontrak') }}</label>
                                <input type="date" wire:model="tanggalBerakhirKontrak"
                                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                                @error('tanggalBerakhirKontrak') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_gaji_pokok') }}</label>
                            <input type="number" min="0" step="0.01" wire:model="gajiPokok"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            @error('gajiPokok') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_no_rekening') }}</label>
                            <input type="text" wire:model="noRekening"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_npwp') }}</label>
                            <input type="text" wire:model="npwp"
                                   class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">{{ __('umum.keterangan') }}</label>
                            <textarea wire:model="catatan" rows="2"
                                      class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Akun & foto --}}
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('hr.bagian_akun_foto') }}</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_tautan_akun') }}</label>
                            @php
                                $opsiPengguna = $this->penggunaBelumTertaut
                                    ->map(fn ($u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->email})"])
                                    ->all();
                            @endphp
                            <div class="mt-1">
                                <x-pilih-cari :opsi="$opsiPengguna" :nilai="$userId" set="userId" bisa-kosong
                                              placeholder="{{ __('hr.cari_akun_pengguna') }}" />
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ __('hr.ket_tautan_akun') }}</p>
                            @error('userId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_foto_karyawan') }}</label>
                            @if ($fotoKaryawanLama && ! $fotoKaryawan)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($fotoKaryawanLama) }}" class="mt-1 h-20 rounded-lg border border-gray-200">
                            @endif
                            {{-- isPreviewable() dulu, bukan langsung temporaryUrl(): berkas yang
                                 bukan gambar (mis. dipilih keliru, belum sempat divalidasi
                                 server) akan melempar galat kalau dipaksa dibuatkan pratinjau. --}}
                            @if ($fotoKaryawan && $fotoKaryawan->isPreviewable())
                                <img src="{{ $fotoKaryawan->temporaryUrl() }}" class="mt-1 h-20 rounded-lg border border-gray-200">
                            @endif
                            <input type="file" accept="image/*" wire:model="fotoKaryawan"
                                   class="mt-1 block w-full text-sm text-gray-700 file:mr-2 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700">
                            <div wire:loading wire:target="fotoKaryawan" class="mt-1 text-xs text-gray-500">{{ __('umum.mengunggah') }}</div>
                            @error('fotoKaryawan') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('hr.atr_foto_ktp') }}</label>
                            @if ($fotoKtpLama && ! $fotoKtp)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($fotoKtpLama) }}" class="mt-1 h-20 rounded-lg border border-gray-200">
                            @endif
                            @if ($fotoKtp && $fotoKtp->isPreviewable())
                                <img src="{{ $fotoKtp->temporaryUrl() }}" class="mt-1 h-20 rounded-lg border border-gray-200">
                            @endif
                            <input type="file" accept="image/*" wire:model="fotoKtp"
                                   class="mt-1 block w-full text-sm text-gray-700 file:mr-2 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700">
                            <div wire:loading wire:target="fotoKtp" class="mt-1 text-xs text-gray-500">{{ __('umum.mengunggah') }}</div>
                            @error('fotoKtp') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="aktif" class="rounded text-blue-600">
                    {{ __('hr.karyawan_aktif') }}
                </label>
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan,fotoKaryawan,fotoKtp"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">{{ __('umum.simpan') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @if ($konfirmasiHapus)
        <x-modal :judul="__('hr.judul_hapus_karyawan')" tutup="$set('konfirmasiHapus', null)">
            <p class="p-5 text-sm text-gray-600">{{ __('hr.ket_hapus_karyawan') }}</p>
            <x-slot:aksi>
                <button type="button" wire:click="$set('konfirmasiHapus', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="hapus({{ $konfirmasiHapus }})"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('umum.hapus') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
