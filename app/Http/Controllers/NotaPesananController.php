<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
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
            'untukPdf' => false,
        ]);
    }

    /**
     * Opsi kedua di menu cetak (meniru "Unduh PDF" Accurate): berkas PDF asli
     * dari server lewat Dompdf, bukan sekadar "print to PDF" bawaan browser.
     * View-nya sama persis dengan yang dipakai tombol Cetak — layout memakai
     * display:table, bukan flex, supaya Dompdf merendernya identik.
     */
    public function unduhPdf(Pesanan $pesanan): Response
    {
        abort_unless($pesanan->status->bisaDicetak(), 403, __('pesanan.galat_tak_bisa_cetak'));

        $pesanan->load(['items.produk:id,nama', 'toko']);

        $pdf = Pdf::loadView('cetak.nota-pesanan', [
            'pesanan' => $pesanan,
            'pencetak' => Auth::user(),
            'untukPdf' => true,
        ]);

        return $pdf->download('Nota-'.str_replace('/', '-', $pesanan->kode).'.pdf');
    }
}
