<?php

namespace App\Http\Middleware;

use App\Support\DepotContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan depot aktif untuk permintaan ini, sebelum komponen Livewire
 * manapun sempat menjalankan query yang di-scope App\Models\Scopes\DepotScope.
 *
 * Dipasang di level grup rute 'auth' (routes/web.php), bukan global lewat
 * bootstrap/app.php seperti AturBahasa — tidak ada satupun rute tamu
 * (masuk, ganti bahasa, keluar, dua rute cetak bertanda tangan) yang
 * butuh depot ter-resolve, jadi tidak perlu diperiksa null di sini.
 */
class TentukanDepot
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if ($pengguna !== null) {
            DepotContext::pakaiUntukPermintaan($pengguna, $request->session());
        }

        return $next($request);
    }
}
