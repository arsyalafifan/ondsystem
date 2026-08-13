@props(['status'])

@php
    $status = $status instanceof \App\Enums\StatusBayar ? $status : \App\Enums\StatusBayar::from($status);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.$status->badge()]) }}>
    {{ $status->label() }}
</span>
