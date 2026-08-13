@props(['ikon' => 'inbox', 'judul', 'keterangan' => null])

<div class="px-4 py-12 text-center">
    @svg('heroicon-o-'.$ikon, ['class' => 'size-12 mx-auto text-gray-400'])
    <p class="mt-4 text-sm font-medium text-gray-900">{{ $judul }}</p>
    @if ($keterangan)
        <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">{{ $keterangan }}</p>
    @endif
    @if (isset($aksi))
        <div class="mt-4 flex justify-center gap-2">{{ $aksi }}</div>
    @endif
</div>
