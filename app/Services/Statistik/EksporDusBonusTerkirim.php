<?php

namespace App\Services\Statistik;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Menyusun berkas Excel (.xlsx) untuk laporan Dus Bonus Terkirim:
 * 1. Sheet 1: Ringkasan per Varian Produk (ranking varian, total dus bonus, jumlah transaksi & toko).
 * 2. Sheet 2: Detail Riwayat (baris per item bonus terkirim beserta informasi pesanan, toko, dan tipe bonus).
 */
final class EksporDusBonusTerkirim
{
    private const WARNA_HEADER = '6D28D9'; // Ungu violet elegan

    private const WARNA_TOTAL = 'EDE9FE'; // Ungu lembut untuk baris total

    /**
     * @param string $periodeLabel Keterangan rentang waktu/periode laporan
     * @param iterable<int, array{no: int, kode: string, nama: string, total_dus: int, transaksi: int, toko: int, porsi: float}> $ringkasan
     * @param iterable<int, array{no: int, tanggal: string, kode_pesanan: string, toko: string, wilayah: string, kategori: string, pelaksana: string, kode_produk: string, nama_produk: string, qty: int, tipe_bonus: string}> $riwayat
     */
    public function buat(string $periodeLabel, iterable $ringkasan, iterable $riwayat): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        // ==========================================
        // SHEET 1: RINGKASAN PER VARIAN
        // ==========================================
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Ringkasan per Varian');

        // Judul Laporan
        $sheet1->mergeCells('A1:G1');
        $sheet1->setCellValue('A1', 'LAPORAN DUS BONUS TERKIRIM - PER VARIAN');
        $sheet1->getStyle('A1')->getFont()->setSize(16)->setBold(true);
        $sheet1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet1->mergeCells('A2:G2');
        $sheet1->setCellValue('A2', 'Periode: ' . $periodeLabel);
        $sheet1->getStyle('A2')->getFont()->setSize(11)->setItalic(true)->getColor()->setRGB('4B5563');

        // Header Kolom Sheet 1
        $headers1 = [
            'A' => ['No', 6, Alignment::HORIZONTAL_CENTER],
            'B' => ['Kode Produk', 15, Alignment::HORIZONTAL_LEFT],
            'C' => ['Nama Produk / Varian', 32, Alignment::HORIZONTAL_LEFT],
            'D' => ['Dus Bonus Terkirim', 20, Alignment::HORIZONTAL_RIGHT],
            'E' => ['Jumlah Transaksi', 18, Alignment::HORIZONTAL_RIGHT],
            'F' => ['Jumlah Toko', 16, Alignment::HORIZONTAL_RIGHT],
            'G' => ['Porsi (%)', 14, Alignment::HORIZONTAL_RIGHT],
        ];

        $barisHeader1 = 4;
        foreach ($headers1 as $kolom => [$label, $lebar, $align]) {
            $sheet1->setCellValue("{$kolom}{$barisHeader1}", $label);
            $sheet1->getColumnDimension($kolom)->setWidth($lebar);
            $sheet1->getStyle("{$kolom}{$barisHeader1}")->getAlignment()->setHorizontal($align);
        }

        $styleHeader1 = $sheet1->getStyle("A{$barisHeader1}:G{$barisHeader1}");
        $styleHeader1->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $styleHeader1->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_HEADER);
        $styleHeader1->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet1->getRowDimension($barisHeader1)->setRowHeight(26);

        // Isi Data Sheet 1
        $baris = $barisHeader1 + 1;
        $totalDus = 0;
        $totalTransaksi = 0;
        $totalToko = 0;

        foreach ($ringkasan as $r) {
            $sheet1->setCellValueExplicit("A{$baris}", $r['no'], DataType::TYPE_NUMERIC);
            $sheet1->setCellValueExplicit("B{$baris}", $r['kode'], DataType::TYPE_STRING);
            $sheet1->setCellValue("C{$baris}", $r['nama']);
            $sheet1->setCellValueExplicit("D{$baris}", $r['total_dus'], DataType::TYPE_NUMERIC);
            $sheet1->setCellValueExplicit("E{$baris}", $r['transaksi'], DataType::TYPE_NUMERIC);
            $sheet1->setCellValueExplicit("F{$baris}", $r['toko'], DataType::TYPE_NUMERIC);
            $sheet1->setCellValueExplicit("G{$baris}", sprintf('%.1f%%', $r['porsi']), DataType::TYPE_STRING);

            $sheet1->getStyle("A{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("B{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet1->getStyle("C{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet1->getStyle("D{$baris}:F{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet1->getStyle("G{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $totalDus += $r['total_dus'];
            $totalTransaksi += $r['transaksi'];
            $totalToko += $r['toko'];
            $baris++;
        }

        if ($baris === $barisHeader1 + 1) {
            $sheet1->mergeCells("A{$baris}:G{$baris}");
            $sheet1->setCellValue("A{$baris}", 'Tidak ada data dus bonus pada periode ini.');
            $sheet1->getStyle("A{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("A{$baris}")->getFont()->setItalic(true);
            $baris++;
        } else {
            // Baris Total
            $sheet1->mergeCells("A{$baris}:C{$baris}");
            $sheet1->setCellValue("A{$baris}", 'TOTAL KESELURUHAN');
            $sheet1->setCellValueExplicit("D{$baris}", $totalDus, DataType::TYPE_NUMERIC);
            $sheet1->setCellValueExplicit("E{$baris}", $totalTransaksi, DataType::TYPE_NUMERIC);
            $sheet1->setCellValueExplicit("F{$baris}", $totalToko, DataType::TYPE_NUMERIC);
            $sheet1->setCellValue("G{$baris}", '100%');

            $styleTotal = $sheet1->getStyle("A{$baris}:G{$baris}");
            $styleTotal->getFont()->setBold(true);
            $styleTotal->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_TOTAL);
            $sheet1->getStyle("A{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("D{$baris}:G{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet1->getRowDimension($baris)->setRowHeight(22);
            $baris++;
        }

        // Border Sheet 1
        $sheet1->getStyle("A{$barisHeader1}:G" . ($baris - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');

        // ==========================================
        // SHEET 2: DETAIL RIWAYAT
        // ==========================================
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Detail Riwayat');

        // Judul Laporan Sheet 2
        $sheet2->mergeCells('A1:K1');
        $sheet2->setCellValue('A1', 'DETAIL RIWAYAT PENGIRIMAN DUS BONUS');
        $sheet2->getStyle('A1')->getFont()->setSize(16)->setBold(true);

        $sheet2->mergeCells('A2:K2');
        $sheet2->setCellValue('A2', 'Periode: ' . $periodeLabel);
        $sheet2->getStyle('A2')->getFont()->setSize(11)->setItalic(true)->getColor()->setRGB('4B5563');

        $headers2 = [
            'A' => ['No', 6, Alignment::HORIZONTAL_CENTER],
            'B' => ['Tanggal', 14, Alignment::HORIZONTAL_CENTER],
            'C' => ['No. Pesanan', 18, Alignment::HORIZONTAL_LEFT],
            'D' => ['Nama Toko', 28, Alignment::HORIZONTAL_LEFT],
            'E' => ['Wilayah', 18, Alignment::HORIZONTAL_LEFT],
            'F' => ['Kategori', 16, Alignment::HORIZONTAL_CENTER],
            'G' => ['Driver / Sales', 22, Alignment::HORIZONTAL_LEFT],
            'H' => ['Kode Produk', 14, Alignment::HORIZONTAL_LEFT],
            'I' => ['Nama Produk / Varian', 30, Alignment::HORIZONTAL_LEFT],
            'J' => ['Dus Terkirim', 14, Alignment::HORIZONTAL_RIGHT],
            'K' => ['Tipe Bonus', 15, Alignment::HORIZONTAL_CENTER],
        ];

        $barisHeader2 = 4;
        foreach ($headers2 as $kolom => [$label, $lebar, $align]) {
            $sheet2->setCellValue("{$kolom}{$barisHeader2}", $label);
            $sheet2->getColumnDimension($kolom)->setWidth($lebar);
            $sheet2->getStyle("{$kolom}{$barisHeader2}")->getAlignment()->setHorizontal($align);
        }

        $styleHeader2 = $sheet2->getStyle("A{$barisHeader2}:K{$barisHeader2}");
        $styleHeader2->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $styleHeader2->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_HEADER);
        $styleHeader2->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet2->getRowDimension($barisHeader2)->setRowHeight(26);

        $baris2 = $barisHeader2 + 1;
        $totalDusDetail = 0;

        foreach ($riwayat as $rw) {
            $sheet2->setCellValueExplicit("A{$baris2}", $rw['no'], DataType::TYPE_NUMERIC);
            $sheet2->setCellValueExplicit("B{$baris2}", $rw['tanggal'], DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit("C{$baris2}", $rw['kode_pesanan'], DataType::TYPE_STRING);
            $sheet2->setCellValue("D{$baris2}", $rw['toko']);
            $sheet2->setCellValue("E{$baris2}", $rw['wilayah']);
            $sheet2->setCellValue("F{$baris2}", $rw['kategori']);
            $sheet2->setCellValue("G{$baris2}", $rw['pelaksana']);
            $sheet2->setCellValueExplicit("H{$baris2}", $rw['kode_produk'], DataType::TYPE_STRING);
            $sheet2->setCellValue("I{$baris2}", $rw['nama_produk']);
            $sheet2->setCellValueExplicit("J{$baris2}", $rw['qty'], DataType::TYPE_NUMERIC);
            $sheet2->setCellValue("K{$baris2}", $rw['tipe_bonus']);

            $sheet2->getStyle("A{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("B{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("C{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet2->getStyle("D{$baris2}:E{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet2->getStyle("F{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("G{$baris2}:I{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet2->getStyle("J{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getStyle("K{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $totalDusDetail += $rw['qty'];
            $baris2++;
        }

        if ($baris2 === $barisHeader2 + 1) {
            $sheet2->mergeCells("A{$baris2}:K{$baris2}");
            $sheet2->setCellValue("A{$baris2}", 'Tidak ada detail riwayat pada periode ini.');
            $sheet2->getStyle("A{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("A{$baris2}")->getFont()->setItalic(true);
            $baris2++;
        } else {
            // Baris Total Riwayat
            $sheet2->mergeCells("A{$baris2}:I{$baris2}");
            $sheet2->setCellValue("A{$baris2}", 'TOTAL DUS BONUS TERKIRIM');
            $sheet2->setCellValueExplicit("J{$baris2}", $totalDusDetail, DataType::TYPE_NUMERIC);
            $sheet2->setCellValue("K{$baris2}", '');

            $styleTotal2 = $sheet2->getStyle("A{$baris2}:K{$baris2}");
            $styleTotal2->getFont()->setBold(true);
            $styleTotal2->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_TOTAL);
            $sheet2->getStyle("A{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("J{$baris2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getRowDimension($baris2)->setRowHeight(22);
            $baris2++;
        }

        $sheet2->getStyle("A{$barisHeader2}:K" . ($baris2 - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');

        // Kembalikan fokus ke Sheet 1
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
