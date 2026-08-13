<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi sebuah rute untuk peran tertentu saja.
 * Contoh pemakaian: ->middleware('peran:admin,sales')
 */
class PastikanPeran
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $pengguna = $request->user();

        if ($pengguna === null) {
            abort(403, 'Halaman ini tidak tersedia untuk peran Anda.');
        }

        // Superadmin bisa membuka semua halaman yang bisa dibuka admin, jadi
        // ia otomatis lolos dari daftar peran mana pun tanpa perlu disebut
        // di setiap rute satu per satu.
        if ($pengguna->isSuperadmin()) {
            return $next($request);
        }

        if (! in_array($pengguna->role->value, $peran, true)) {
            abort(403, 'Halaman ini tidak tersedia untuk peran Anda.');
        }

        return $next($request);
    }
}
