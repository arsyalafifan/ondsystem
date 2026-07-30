@props(['judul', 'keterangan' => null])

<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-gray-900">{{ $judul }}</h1>
        @if ($keterangan)
            <p class="mt-0.5 text-sm text-gray-500">{{ $keterangan }}</p>
        @endif
    </div>

    @if (isset($aksi))
        <div class="flex flex-wrap items-center gap-2">{{ $aksi }}</div>
    @endif
</div>
