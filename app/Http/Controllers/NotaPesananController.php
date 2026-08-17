<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use App\Models\User;
use App\Support\EscpNotaBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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

    /**
     * Opsi ketiga, terpisah total dari Cetak/Unduh PDF di atas — sengaja
     * tidak mengubah keduanya sama sekali supaya tetap ada jalur cadangan
     * bila opsi ini bermasalah. Ini bukan berkas untuk dibuka lalu di-print
     * lewat dialog cetak biasa (itu akan dirasterisasi lagi oleh GDI, balik
     * ke masalah semula) — kirim APA ADANYA ke printer, misalnya lewat
     * antrean printer "Generic / Text Only" di port yang sama, atau
     * `copy /b nama-berkas.prn USB001` dari Command Prompt.
     */
    public function unduhEscp(Pesanan $pesanan): Response
    {
        abort_unless($pesanan->status->bisaDicetak(), 403, __('pesanan.galat_tak_bisa_cetak'));

        $pesanan->load(['items.produk:id,nama', 'toko']);

        $isi = EscpNotaBuilder::build($pesanan, Auth::user());

        return response($isi, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="Nota-'.str_replace('/', '-', $pesanan->kode).'.prn"',
        ]);
    }

    /**
     * Sumber data untuk OND Print Helper (.exe Windows) — dipanggil lewat
     * link "ondprint://" yang diklik dari tombol "Cetak Langsung ke Printer",
     * bukan dari sesi browser biasa, jadi tidak ada Auth::user(). Identitas
     * pencetak dan hak akses sepenuhnya ditentukan saat link ditandatangani
     * di NotaPesananController::cetak() (lihat nota-pesanan.blade.php),
     * bukan di sini — middleware `signed` di rute yang menolak permintaan
     * dengan tanda tangan tidak valid atau sudah kedaluwarsa.
     */
    public function escpUntukAgenCetak(Request $request, Pesanan $pesanan): Response
    {
        abort_unless($pesanan->status->bisaDicetak(), 403, __('pesanan.galat_tak_bisa_cetak'));

        $pencetak = User::findOrFail($request->query('pencetak'));

        $pesanan->load(['items.produk:id,nama', 'toko']);

        return response(EscpNotaBuilder::build($pesanan, $pencetak), 200, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
