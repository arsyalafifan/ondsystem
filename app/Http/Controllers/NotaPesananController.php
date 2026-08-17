<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

final class NotaPesananController extends Controller
{
    public function cetak(Pesanan $pesanan): View
    {
        abort_unless($pesanan->status->bisaDicetak(), 403, __('pesanan.galat_tak_bisa_cetak'));

        $pesanan->load(['items.produk:id,nama', 'toko']);

        return view('cetak.nota-pesanan', [
            'pesanan' => $pesanan,
            'pencetak' => Auth::user(),
        ]);
    }
}
