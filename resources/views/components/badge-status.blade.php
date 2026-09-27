@props(['status'])

@php
    // Diterima langsung sebagai enum apa pun yang punya label()+badge()
    // (StatusPesanan, StatusNoo, dst.) — string mentah masih ditebak sebagai
    // StatusPesanan demi kompatibilitas pemakaian lama.
    $status = is_string($status) ? \App\Enums\StatusPesanan::from($status) : $status;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.$status->badge()]) }}>
    {{ $status->label() }}
</span>
