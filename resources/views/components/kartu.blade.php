@props(['judul' => null, 'aksi' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white shadow-sm']) }}>
    @if ($judul || $aksi)
        <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
            <h2 class="text-sm font-semibold text-gray-900">{{ $judul }}</h2>
            @if ($aksi)
                <div class="flex items-center gap-2">{{ $aksi }}</div>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
