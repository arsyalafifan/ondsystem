@props(['dus' => 0, 'nilai' => 0, 'target', 'label'])

{{-- Ringkasan melayang di bawah layar HP (ala keranjang aplikasi pesan-antar):
     total dus & nilai, plus tombol menuju panel simpan. Di layar lebar tidak
     perlu — panel simpannya sudah menempel di samping. --}}
@if ($dus > 0)
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_16px_rgba(0,0,0,0.06)] backdrop-blur lg:hidden">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs text-gray-500">@angka($dus) {{ __('umum.satuan_dus') }}</p>
                <p class="truncate text-base font-semibold tabular-nums text-gray-900">@rupiah($nilai)</p>
            </div>
            <button type="button"
                    onclick="document.getElementById(@js($target))?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    class="shrink-0 rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                {{ $label }}
            </button>
        </div>
    </div>
@endif
