@php
    $rekap = $this->rekap;
    $logo = is_file(public_path(\App\Services\Statistik\EksporFormPembelianProduk::LOGO));
    $inputKelas = 'mt-1 block rounded-lg border-gray-400 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 shadow-sm transition-all focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20';
@endphp

<div>
    <x-judul-halaman :judul="__('statistik.judul_form_pembelian')" :keterangan="__('statistik.ket_form_pembelian')">
        <x-slot:aksi>
            <button type="button" wire:click="unduhExcel" wire:loading.attr="disabled" wire:target="unduhExcel"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                <x-heroicon-o-arrow-down-tray class="size-4" />
                <span wire:loading.remove wire:target="unduhExcel">{{ __('statistik.unduh_excel') }}</span>
                <span wire:loading wire:target="unduhExcel">{{ __('umum.memproses') }}</span>
            </button>
        </x-slot:aksi>
    </x-judul-halaman>

    <x-kartu>
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <div class="flex flex-wrap rounded-lg border border-gray-300 bg-gray-50 p-1">
                @foreach (\App\Livewire\Statistik\FormPembelianProduk::MODE as $nilai)
                    <button type="button" wire:click="$set('mode', '{{ $nilai }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-all',
                                'bg-white text-blue-700 shadow-sm' => $mode === $nilai,
                                'text-gray-500 hover:text-gray-700' => $mode !== $nilai,
                            ])>
                        {{ __('statistik.mode_'.$nilai) }}
                    </button>
                @endforeach
            </div>

            @if ($mode === 'hari')
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('umum.tanggal') }}</label>
                    <input type="date" wire:model.live="tanggal" class="{{ $inputKelas }}">
                </div>
            @elseif ($mode === 'bulan')
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('statistik.bulan') }}</label>
                    <input type="month" wire:model.live="bulan" class="{{ $inputKelas }}">
                </div>
            @elseif ($mode === 'tahun')
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('statistik.tahun') }}</label>
                    <input type="number" min="2000" max="2100" wire:model.live.debounce.500ms="tahun" class="{{ $inputKelas }} w-28">
                </div>
            @elseif ($mode === 'rentang')
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('statistik.dari_tanggal') }}</label>
                    <input type="date" wire:model.live="dariTanggal" class="{{ $inputKelas }}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">{{ __('statistik.sampai_tanggal') }}</label>
                    <input type="date" wire:model.live="sampaiTanggal" class="{{ $inputKelas }}">
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('statistik.total_dus_periode') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700">@angka($rekap['total_dus']) {{ __('umum.satuan_dus') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('statistik.jumlah_toko') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka(count($rekap['baris']))</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ __('statistik.jumlah_toko_membeli') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">@angka(collect($rekap['baris'])->where('total', '>', 0)->count())</p>
            </div>
        </div>

        {{--
            Meniru tata letak form Excel (lihat EksporFormPembelianProduk):
            judul dwibahasa, header hijau dua baris yang menempel di atas saat
            digulir, sel tanggal kosong bila tidak ada pembelian. Warna dan
            garis ditulis sebagai CSS di sini, bukan kelas Tailwind arbitrer,
            supaya tidak bergantung pada build aset ulang.
        --}}
        <style>
            .form-pembelian table { border-collapse: separate; border-spacing: 0; font-size: 11px; }
            .form-pembelian th, .form-pembelian td { border-right: 1px solid #6b7280; border-bottom: 1px solid #6b7280; padding: 2px 6px; text-align: center; white-space: nowrap; }
            .form-pembelian tr > :first-child { border-left: 1px solid #6b7280; }
            .form-pembelian thead th { background: #{{ \App\Services\Statistik\EksporFormPembelianProduk::HIJAU }}; color: #111827; font-weight: 700; position: sticky; z-index: 2; line-height: 1.15; }
            .form-pembelian thead tr:first-child th { top: 0; height: 40px; border-top: 1px solid #6b7280; }
            .form-pembelian thead tr:last-child th { top: 40px; min-width: 26px; }
            .form-pembelian tbody td { background: #fff; color: #111827; }
            .form-pembelian tbody tr:hover td { background: #f0fdf4; }
        </style>

        <div class="form-pembelian border-t border-gray-200 p-4">
            <div class="relative mb-3 text-center text-gray-900">
                @if ($logo)
                    <img src="{{ asset(\App\Services\Statistik\EksporFormPembelianProduk::LOGO) }}" alt="halocoko"
                         class="absolute left-0 top-0 hidden h-14 sm:block">
                @endif
                <p class="text-xl font-bold" style="font-family: SimSun, 'Songti SC', serif">{{ $rekap['judul_zh'] }}</p>
                <p class="mt-1 text-lg font-bold" style="font-family: 'Courier New', monospace">{{ $rekap['judul_id'] }}</p>
            </div>

            @if (count($rekap['baris']) === 0)
                <x-kosong ikon="table-cells" :judul="__('statistik.kosong_form_pembelian')" />
            @else
                <div class="overflow-auto rounded" style="max-height: 72vh">
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="2">NO序号</th>
                                <th rowspan="2">SALES<br>业务员</th>
                                <th rowspan="2">IDN FREEZER<br>冰柜编号</th>
                                <th rowspan="2">NAMA TOKO<br>终端店名</th>
                                <th rowspan="2">KATEGORI<br>类别</th>
                                <th rowspan="2">Alamat Toko<br>终端地址</th>
                                <th rowspan="2">NAMA PEMILIK TOKO<br>店主姓名</th>
                                <th rowspan="2">NO TELP<br>终端电话</th>
                                <th rowspan="2">KOORDINAT<br>坐标</th>
                                @foreach ($rekap['bulan'] as $bulan)
                                    <th colspan="{{ count($bulan['tanggal']) }}">{{ $bulan['label'] }}</th>
                                @endforeach
                                <th rowspan="2">合计<br>Total</th>
                            </tr>
                            <tr>
                                @foreach ($rekap['bulan'] as $bulan)
                                    @foreach ($bulan['tanggal'] as $tanggal)
                                        <th>{{ (int) substr($tanggal, 8, 2) }}</th>
                                    @endforeach
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rekap['baris'] as $toko)
                                <tr wire:key="fp-{{ $toko['no'] }}">
                                    <td class="tabular-nums">{{ $toko['no'] }}</td>
                                    <td>{{ $toko['sales'] }}</td>
                                    <td class="font-mono">{{ $toko['asset_id'] }}</td>
                                    <td>{{ $toko['nama'] }}</td>
                                    <td>{{ $toko['kategori'] }}</td>
                                    <td class="max-w-72 truncate" title="{{ $toko['alamat'] }}">{{ $toko['alamat'] }}</td>
                                    <td>{{ $toko['pemilik'] }}</td>
                                    <td class="tabular-nums">{{ $toko['telepon'] }}</td>
                                    <td>
                                        @if ($toko['latitude'] !== null && $toko['longitude'] !== null)
                                            <a href="https://www.google.com/maps?q={{ $toko['latitude'] }},{{ $toko['longitude'] }}"
                                               target="_blank" rel="noopener" class="text-blue-700 underline">
                                                {{ $toko['latitude'] }}, {{ $toko['longitude'] }}
                                            </a>
                                        @endif
                                    </td>
                                    @foreach ($rekap['bulan'] as $bulan)
                                        @foreach ($bulan['tanggal'] as $tanggal)
                                            <td class="tabular-nums">{{ ($toko['harian'][$tanggal] ?? 0) ?: '' }}</td>
                                        @endforeach
                                    @endforeach
                                    <td class="font-semibold tabular-nums">{{ $toko['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-kartu>
</div>
