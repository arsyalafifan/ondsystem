<?php

namespace App\Http\Controllers;

use App\Models\Kendaraan;
use App\Support\EscpPackingListBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

final class PackingListController extends Controller
{
    public function cetak(Kendaraan $kendaraan): View
    {
        abort_unless($kendaraan->batch->isDisetujui(), 403, __('routing.galat_tak_bisa_cetak_packing_list'));

        $kendaraan->load(['batch', 'stops' => fn ($q) => $q->orderBy('urutan'), 'stops.toko', 'stops.pesanan.items.produk:id,nama']);

        return view('cetak.packing-list-kendaraan', [
            'kendaraan' => $kendaraan,
            'untukPdf' => false,
        ]);
    }

    /**
     * Opsi kedua di menu cetak (meniru "Unduh PDF" Accurate): berkas PDF asli
     * dari server lewat Dompdf, bukan sekadar "print to PDF" bawaan browser.
     * View-nya sama persis dengan yang dipakai tombol Cetak — layout memakai
     * display:table, bukan flex, supaya Dompdf merendernya identik.
     */
    public function unduhPdf(Kendaraan $kendaraan): Response
    {
        abort_unless($kendaraan->batch->isDisetujui(), 403, __('routing.galat_tak_bisa_cetak_packing_list'));

        $kendaraan->load(['batch', 'stops' => fn ($q) => $q->orderBy('urutan'), 'stops.toko', 'stops.pesanan.items.produk:id,nama']);

        $pdf = Pdf::loadView('cetak.packing-list-kendaraan', [
            'kendaraan' => $kendaraan,
            'untukPdf' => true,
        ]);

        return $pdf->download('Packing-List-'.str_replace('/', '-', $kendaraan->batch->kode).'-'.$kendaraan->nama.'.pdf');
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
    public function unduhEscp(Kendaraan $kendaraan): Response
    {
        abort_unless($kendaraan->batch->isDisetujui(), 403, __('routing.galat_tak_bisa_cetak_packing_list'));

        $kendaraan->load(['batch', 'stops' => fn ($q) => $q->orderBy('urutan'), 'stops.toko', 'stops.pesanan.items.produk:id,nama']);

        $isi = EscpPackingListBuilder::build($kendaraan);

        return response($isi, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="Packing-List-'.str_replace('/', '-', $kendaraan->batch->kode).'-'.$kendaraan->nama.'.prn"',
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
    public function escpUntukAgenCetak(Kendaraan $kendaraan): Response
    {
        abort_unless($kendaraan->batch->isDisetujui(), 403, __('routing.galat_tak_bisa_cetak_packing_list'));

        $kendaraan->load(['batch', 'stops' => fn ($q) => $q->orderBy('urutan'), 'stops.toko', 'stops.pesanan.items.produk:id,nama']);

        return response(EscpPackingListBuilder::build($kendaraan), 200, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
