<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use App\Support\EscpNotaBuilder;
use App\Support\TokenCetakSekaliPakai;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class NotaPesananController extends Controller
{
    public function cetak(Pesanan $pesanan): View
    {
        abort_unless($pesanan->status->bisaDicetak(), 403, __('pesanan.galat_tak_bisa_cetak'));

        $pesanan->load(['items.produk:id,nama', 'toko', 'pembuat']);

        return view('cetak.nota-pesanan', [
            'pesanan' => $pesanan,
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

        $pesanan->load(['items.produk:id,nama', 'toko', 'pembuat']);

        $pdf = Pdf::loadView('cetak.nota-pesanan', [
            'pesanan' => $pesanan,
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

        $pesanan->load(['items.produk:id,nama', 'toko', 'pembuat']);

        $isi = EscpNotaBuilder::build($pesanan);

        return response($isi, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="Nota-'.str_replace('/', '-', $pesanan->kode).'.prn"',
        ]);
    }

    /**
     * Sumber data untuk OND Print Helper (.exe Windows) — dipanggil lewat
     * link "ondprint://" yang diklik dari tombol "Cetak Langsung ke Printer",
     * bukan dari sesi browser biasa. Hak akses sepenuhnya ditentukan oleh
     * middleware `signed` di rute (menolak tanda tangan tidak valid atau
     * kedaluwarsa) — bukan siapa yang sedang login, karena memang tidak ada
     * sesi login sama sekali di jalur ini.
     */
    public function escpUntukAgenCetak(Pesanan $pesanan, Request $request): Response
    {
        abort_unless($pesanan->status->bisaDicetak(), 403, __('pesanan.galat_tak_bisa_cetak'));

        // Menutup celah "tercetak dua kali": token dari link ondprint://
        // hanya boleh dipakai sekali, apa pun yang membuat URL ini diminta
        // berulang — lihat komentar di TokenCetakSekaliPakai.
        abort_unless(
            TokenCetakSekaliPakai::pakai($request->query('token')),
            410,
            __('pesanan.galat_link_cetak_sudah_dipakai'),
        );

        $pesanan->load(['items.produk:id,nama', 'toko', 'pembuat']);

        return response(EscpNotaBuilder::build($pesanan), 200, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
