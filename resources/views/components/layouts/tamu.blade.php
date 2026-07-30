@php $bahasa = \App\Support\Bahasa::info(); @endphp
<!DOCTYPE html>
<html lang="{{ $bahasa['html'] }}" dir="{{ $bahasa['arah'] }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('auth.judul') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="grid h-full place-items-center bg-slate-900 p-4 font-sans text-gray-900 antialiased">
    <div class="w-full max-w-sm">
        {{ $slot }}

        <div class="mt-4">
            <x-pemilih-bahasa gaya="terang" />
        </div>
    </div>
    @livewireScripts
</body>
</html>
