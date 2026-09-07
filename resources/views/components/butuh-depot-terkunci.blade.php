{{--
    Ditampilkan menggantikan halaman yang butuh satu depot terkunci
    (membuat pesanan, generate routing, dst), saat superadmin sedang di
    mode "Semua Depot". Lihat App\Livewire\Concerns\MembutuhkanDepotTerkunci.
--}}
<div class="px-4 py-16 text-center">
    @svg('heroicon-o-building-storefront', ['class' => 'size-12 mx-auto text-gray-400'])
    <p class="mt-4 text-sm font-medium text-gray-900">{{ __('umum.butuh_depot_judul') }}</p>
    <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">{{ __('umum.butuh_depot_ket') }}</p>
</div>
