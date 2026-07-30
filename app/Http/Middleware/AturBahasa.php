<?php

namespace App\Http\Middleware;

use App\Support\Bahasa;
use Carbon\CarbonImmutable as Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan bahasa aplikasi pada setiap permintaan, sebelum controller
 * maupun komponen Livewire dijalankan.
 */
class AturBahasa
{
    public function handle(Request $request, Closure $next): Response
    {
        $kode = Bahasa::pilihan($request->user());

        App::setLocale($kode);

        // Nama bulan dan hari pada tanggal ikut berganti bahasa, dan angka
        // memakai pemisah ribuan yang lazim di bahasa tersebut.
        Carbon::setLocale($kode);
        Number::useLocale(str_replace('_', '-', $kode));

        return $next($request);
    }
}
