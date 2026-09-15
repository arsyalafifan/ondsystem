<?php

namespace App\Http\Middleware;

use App\Akses\HakAkses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi rute ke pengguna yang boleh membuka SALAH SATU menu yang disebut.
 * Contoh: ->middleware('akses:ond.pesanan,ond.input_pesanan')
 * Daftar menu & bawaannya: App\Akses\DaftarAkses.
 */
class PastikanAkses
{
    public function handle(Request $request, Closure $next, string ...$menu): Response
    {
        $pengguna = $request->user();

        if ($pengguna === null || ! app(HakAkses::class)->bolehSalahSatu($pengguna, $menu)) {
            abort(403, 'Halaman ini tidak tersedia untuk peran Anda.');
        }

        return $next($request);
    }
}
