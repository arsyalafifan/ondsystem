@props(['url' => null])

@if ($url)
    <img src="{{ $url }}" alt="" loading="lazy" {{ $attributes->merge(['class' => 'bg-gray-100 object-cover']) }}>
@else
    <div {{ $attributes->merge(['class' => 'grid place-items-center bg-gray-100 text-gray-300']) }}>
        <x-heroicon-o-cube class="size-1/2 max-h-10 max-w-10" />
    </div>
@endif
