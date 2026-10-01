@props([
    'produks',
    // Daftar yang SUNGGUH akan disimpan — pemanggil mengosongkan daftar
    // bonus/promo yang tidak berlaku, supaya ringkasan = yang tersimpan.
    'baris' => [],
    'bonus' => [],
    'promo' => [],
    'totalNilai' => 0,
])

@php
    $katalog = $produks->keyBy('id');

    // Satu baris per produk (baris ganda produk yang sama dijumlahkan).
    $susun = function (array $daftar) use ($katalog): array {
        $hasil = [];
        foreach ($daftar as $b) {
            $id = (int) ($b['produk_id'] ?? 0);
            $jumlah = (int) ($b['jumlah_dus'] ?: 0);
            $produk = $katalog->get($id);
            if ($produk === null || $jumlah <= 0) {
                continue;
            }
            $hasil[$id] ??= ['produk' => $produk, 'jumlah' => 0];
            $hasil[$id]['jumlah'] += $jumlah;
        }

        return array_values($hasil);
    };

    $grup = array_values(array_filter([
        ['daftar' => 'baris', 'judul' => null, 'item' => $susun($baris), 'gratis' => false],
        ['daftar' => 'barisBonus', 'judul' => __('pesanan.ringkasan_bonus'), 'item' => $susun($bonus), 'gratis' => true],
        ['daftar' => 'barisPromoBonus', 'judul' => __('pesanan.ringkasan_bonus_promo'), 'item' => $susun($promo), 'gratis' => true],
    ], fn ($g) => $g['item'] !== []));

    $dusReguler = 0;
    $dusBonus = 0;
    $jumlahProduk = 0;
    foreach ($grup as $g) {
        $dus = array_sum(array_column($g['item'], 'jumlah'));
        if ($g['gratis']) {
            $dusBonus += $dus;
        } else {
            $dusReguler += $dus;
            $jumlahProduk = count($g['item']);
        }
    }
@endphp

<x-kartu :judul="__('pesanan.ringkasan_judul')">
    @if ($grup === [])
        <div class="flex flex-col items-center gap-2 px-4 py-8 text-center text-sm text-gray-500">
            <x-heroicon-o-shopping-cart class="size-8 text-gray-300" />
            {{ __('pesanan.ringkasan_kosong') }}
        </div>
    @else
        <div class="max-h-64 divide-y divide-gray-100 overflow-y-auto">
            @foreach ($grup as $g)
                @if ($g['judul'])
                    <p class="bg-emerald-50/60 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ $g['judul'] }}</p>
                @endif
                @foreach ($g['item'] as $i)
                    <div class="flex items-center gap-3 px-4 py-2.5" wire:key="ringkasan-{{ $g['daftar'] }}-{{ $i['produk']->id }}">
                        <x-foto-produk :url="$i['produk']->foto_url" class="size-11 shrink-0 rounded-md" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $i['produk']->nama }}</p>
                            <p class="text-xs text-gray-500 tabular-nums">
                                @if ($g['gratis'])
                                    @angka($i['jumlah']) {{ __('umum.satuan_dus') }}
                                @else
                                    @angka($i['jumlah']) × @rupiah((float) $i['produk']->harga)
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold tabular-nums {{ $g['gratis'] ? 'text-emerald-700' : 'text-gray-900' }}">
                                @if ($g['gratis'])
                                    {{ __('pesanan.ringkasan_gratis') }}
                                @else
                                    @rupiah((float) $i['produk']->harga * $i['jumlah'])
                                @endif
                            </p>
                            <button type="button" wire:click="aturJumlah('{{ $g['daftar'] }}', {{ $i['produk']->id }}, 0)"
                                    class="text-xs text-gray-400 hover:text-red-600">{{ __('pesanan.ringkasan_hapus') }}</button>
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>

        <dl class="space-y-1.5 border-t border-gray-200 px-4 py-3 text-sm">
            <div class="flex justify-between text-gray-600">
                <dt>{{ __('pesanan.ringkasan_subtotal', ['jumlah' => $jumlahProduk]) }}</dt>
                <dd class="tabular-nums">@angka($dusReguler) {{ __('umum.satuan_dus') }}</dd>
            </div>
            @if ($dusBonus > 0)
                <div class="flex justify-between text-emerald-700">
                    <dt>{{ __('pesanan.ringkasan_bonus') }}</dt>
                    <dd class="tabular-nums">+@angka($dusBonus) {{ __('umum.satuan_dus') }}</dd>
                </div>
            @endif
            <div class="flex justify-between text-gray-600">
                <dt>{{ __('pesanan.ringkasan_total_dus') }}</dt>
                <dd class="tabular-nums font-medium text-gray-900">@angka($dusReguler + $dusBonus) {{ __('umum.satuan_dus') }}</dd>
            </div>
            <div class="flex items-baseline justify-between border-t border-dashed border-gray-200 pt-2">
                <dt class="font-semibold text-gray-900">{{ __('pesanan.ringkasan_total_bayar') }}</dt>
                <dd class="text-lg font-bold tabular-nums text-blue-700">@rupiah($totalNilai)</dd>
            </div>
        </dl>
    @endif
</x-kartu>
