<div>
    <x-judul-halaman :judul="__('pengguna.judul')" :keterangan="__('pengguna.ket')">
        <x-slot:aksi>
            <button type="button" wire:click="buatBaru"
                    class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('pengguna.pengguna_baru') }}
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="min-w-56 flex-1">
                <label class="block text-xs font-medium text-gray-600">{{ __('umum.cari') }}</label>
                <input type="search" wire:model.live.debounce.300ms="cari" placeholder="{{ __('pengguna.cari_pengguna') }}"
                       class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('pengguna.atr_akses_gudang') }}</label>
                <select wire:model.live="filterDepot"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    <option value="">{{ __('pengguna.semua_depot_filter') }}</option>
                    <option value="tanpa_depot">{{ __('pengguna.tanpa_depot') }}</option>
                    @foreach ($this->depotUntukFilter as $d)
                        <option value="{{ $d->id }}">{{ $d->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">{{ __('pengguna.atr_peran') }}</label>
                <select wire:model.live="filterPeran"
                        class="mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    <option value="">{{ __('pengguna.semua_peran_filter') }}</option>
                    @foreach ($this->peranCases as $p)
                        <option value="{{ $p->value }}">{{ $p->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('umum.nama') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('pengguna.atr_email') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('pengguna.atr_peran') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('pengguna.atr_akses_gudang') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('pengguna.atr_no_hp') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('umum.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('umum.aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->penggunas as $u)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $u->name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $u->email }}</td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                    'bg-violet-100 text-violet-800 ring-violet-600/20' => $u->role === \App\Enums\PeranPengguna::Superadmin,
                                    'bg-blue-100 text-blue-800 ring-blue-600/20' => $u->role === \App\Enums\PeranPengguna::Admin,
                                    'bg-emerald-100 text-emerald-800 ring-emerald-600/20' => $u->role === \App\Enums\PeranPengguna::Sales,
                                    'bg-amber-100 text-amber-800 ring-amber-600/20' => $u->role === \App\Enums\PeranPengguna::Driver,
                                ])>
                                    {{ $u->role->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-600">
                                @if ($u->isSuperadmin())
                                    <span class="text-xs text-violet-700">{{ __('umum.semua_depot') }}</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($u->depots as $d)
                                            <span @class([
                                                'inline-flex items-center gap-1 whitespace-nowrap rounded-md px-2 py-0.5 text-xs ring-1 ring-inset',
                                                'bg-blue-50 font-medium text-blue-700 ring-blue-600/20' => $d->id === $u->depot_id,
                                                'bg-gray-50 text-gray-600 ring-gray-500/20' => $d->id !== $u->depot_id,
                                            ])>
                                                {{ $d->nama }}
                                                @if ($d->id === $u->depot_id)
                                                    <span class="text-[10px] uppercase">· {{ __('pengguna.label_default') }}</span>
                                                @endif
                                            </span>
                                        @empty
                                            <span class="text-xs text-gray-400">{{ __('pengguna.tanpa_depot') }}</span>
                                        @endforelse
                                    </div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $u->no_hp ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($u->aktif)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('umum.aktif') }}</span>
                                @else
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('umum.nonaktif') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="sunting({{ $u->id }})"
                                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50">
                                        {{ __('umum.sunting') }}
                                    </button>
                                    <button type="button" wire:click="$set('konfirmasiReset', {{ $u->id }})"
                                            class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                        {{ __('pengguna.tombol_reset_sandi') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-kosong ikon="user-group" :judul="__('pengguna.pengguna_kosong')" :keterangan="__('pengguna.pengguna_kosong_ket')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->penggunas->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">{{ $this->penggunas->links() }}</div>
        @endif
    </x-kartu>

    @if ($formTerbuka)
        <x-modal :judul="$penggunaId ? __('pengguna.judul_pengguna_sunting') : __('pengguna.judul_pengguna_baru')" tutup="tutupForm">
            <div class="space-y-3 p-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('pengguna.atr_nama') }}</label>
                    <input type="text" wire:model="name"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('pengguna.atr_email') }}</label>
                    <input type="email" wire:model="email"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('pengguna.atr_peran') }}</label>
                    <select wire:model.live="role"
                            class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        @foreach ($this->peranCases as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($role !== \App\Enums\PeranPengguna::Superadmin->value)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('pengguna.atr_akses_gudang') }}</label>
                        <div class="mt-1 grid max-h-48 grid-cols-1 gap-1 overflow-y-auto rounded-lg border border-gray-300 bg-gray-50 p-2 sm:grid-cols-2">
                            @foreach ($this->depotAktif as $d)
                                <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-700 hover:bg-white">
                                    <input type="checkbox" value="{{ $d->id }}" wire:model.live="depotAkses"
                                           class="rounded border-gray-400 text-blue-600 focus:ring-blue-500/20">
                                    <span class="truncate">{{ $d->nama }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ __('pengguna.ket_akses_gudang') }}</p>
                        @error('depotAkses') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @error('depotAkses.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('pengguna.atr_gudang_default') }}</label>
                        <select wire:model="depotDefault"
                                class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                            <option value="">{{ __('pengguna.default_otomatis') }}</option>
                            @foreach ($this->depotAktif->whereIn('id', array_map('intval', $depotAkses)) as $d)
                                <option value="{{ $d->id }}">{{ $d->nama }}</option>
                            @endforeach
                        </select>
                        @error('depotDefault') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @else
                    <p class="rounded-lg border border-violet-200 bg-violet-50 px-4 py-2.5 text-sm text-violet-800">{{ __('pengguna.ket_akses_superadmin') }}</p>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('pengguna.atr_no_hp') }}</label>
                    <input type="text" wire:model="no_hp"
                           class="mt-1 block w-full rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    @error('no_hp') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="aktif" class="rounded text-blue-600 rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all placeholder:text-gray-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                    {{ __('pengguna.pengguna_aktif') }}
                </label>

                @if (! $penggunaId)
                    <p class="text-xs text-gray-500">{{ __('pengguna.ket_sandi_awal') }}</p>
                @endif
            </div>

            <x-slot:aksi>
                <button type="button" wire:click="tutupForm"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="simpan"
                        class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('umum.simpan') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif

    @if ($konfirmasiReset)
        @php $u = \App\Models\User::withoutGlobalScope(\App\Models\Scopes\DepotScope::class)->find($konfirmasiReset); @endphp
        <x-modal :judul="__('pengguna.judul_reset_sandi')" tutup="$set('konfirmasiReset', null)">
            <p class="p-5 text-sm text-gray-600">{{ __('pengguna.ket_reset_sandi', ['nama' => $u?->name]) }}</p>
            <x-slot:aksi>
                <button type="button" wire:click="$set('konfirmasiReset', null)"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">{{ __('umum.batal') }}</button>
                <button type="button" wire:click="resetSandi({{ $konfirmasiReset }})"
                        class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('pengguna.tombol_reset_sandi') }}</button>
            </x-slot:aksi>
        </x-modal>
    @endif
</div>
