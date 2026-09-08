<?php

namespace App\Http\Middleware;

use App\Support\DepotContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Khusus dua rute cetak bertanda tangan (pesanan.nota.escp.signed,
 * routing.packing-list.escp.signed) yang dipanggil OND Print Helper (.exe
 * Windows) tanpa sesi/login sama sekali — jadi di luar grup rute 'auth'
 * dan tidak pernah melewati TentukanDepot.
 *
 * Route-model binding implisit (Pesanan $pesanan / Kendaraan $kendaraan)
 * di-resolve lewat middleware SubstituteBindings bawaan Laravel SEBELUM
 * isi method controller sempat jalan — jadi konteks depot harus sudah
 * ditetapkan di sini. Urutannya dijamin lewat
 * Middleware::prependToPriorityList() di bootstrap/app.php, bukan
 * mengandalkan urutan penulisan middleware yang ambigu.
 *
 * "Semua depot" di sini aman: signed URL sudah membuktikan otorisasi
 * untuk satu baris spesifik (idnya ada di URL & tanda tangannya valid),
 * dan primary key `id` sendiri tetap unik lintas semua depot — jadi
 * melewati filter depot_id di sini tidak membuka baris manapun selain
 * yang memang sudah ditunjuk signed URL itu.
 */
class SemuaDepotUntukCetak
{
    public function handle(Request $request, Closure $next): Response
    {
        DepotContext::pakaiSemuaDepot();

        return $next($request);
    }
}
